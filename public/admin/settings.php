<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../../app/base_url.php';
require_once __DIR__ . '/../auth/auth_functions.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../app/services/MailService.php';

require_admin();

$pdo = getPDO();
$configPath = realpath(__DIR__ . '/../../config') . '/recette_options.php';
$currentOptions = require $configPath;

function sanitize_option_key(string $value): string
{
    $value = trim(mb_strtolower($value));
    return preg_replace('/[^a-z0-9_-]/', '', $value) ?? '';
}

function sanitize_option_label(string $value): string
{
    return trim($value);
}

function normalize_key_label_pairs(array $keys, array $labels): array
{
    $result = [];
    foreach ($keys as $i => $rawKey) {
        $key = sanitize_option_key((string) $rawKey);
        $label = sanitize_option_label((string) ($labels[$i] ?? ''));
        if ($key === '' || $label === '') {
            continue;
        }
        $result[$key] = $label;
    }
    return $result;
}

function export_options_php(array $options): string
{
    $renderAssoc = static function (array $assoc): string {
        $lines = [];
        foreach ($assoc as $k => $v) {
            $lines[] = "        '" . addslashes((string) $k) . "' => '" . addslashes((string) $v) . "',";
        }
        return implode("\n", $lines);
    };

    return "<?php\n"
        . "declare(strict_types=1);\n\n"
        . "return [\n"
        . "    'categories' => [\n" . $renderAssoc($options['categories'] ?? []) . "\n    ],\n\n"
        . "    'types_cuisson' => [\n" . $renderAssoc($options['types_cuisson'] ?? []) . "\n    ],\n\n"
        . "    'types_recette' => [\n" . $renderAssoc($options['types_recette'] ?? []) . "\n    ],\n"
        . "];\n";
}

function canonical_text_key(string $value): string
{
    $key = trim(mb_strtolower($value));
    $key = strtr($key, [
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'à' => 'a', 'â' => 'a',
        'î' => 'i', 'ï' => 'i',
        'ô' => 'o', 'ö' => 'o',
        'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ]);
    return $key;
}

function build_category_index(array $options): array
{
    $index = [];
    foreach (array_keys($options['categories'] ?? []) as $category) {
        $canonical = canonical_text_key((string) $category);
        if ($canonical === '') {
            continue;
        }
        $index[$canonical] = $category;
        if (!str_ends_with($canonical, 's')) {
            $index[$canonical . 's'] = $category;
        }
    }
    if (isset($index['dessert'])) {
        $index['gateau'] = $index['dessert'];
        $index['gateaux'] = $index['dessert'];
    }
    return $index;
}

function normalize_dedup_value(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9 ]/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim((string) $value);
}

function compute_dedup_hash(string $titre, ?string $auteur, array $ingredients): string
{
    $normTitre = normalize_dedup_value($titre);
    $normAuteur = normalize_dedup_value($auteur ?? '');
    $firstIngredients = array_slice($ingredients, 0, 3);
    $normIngredients = array_map(
        static fn($ing) => normalize_dedup_value((string) $ing),
        $firstIngredients
    );
    $payload = $normTitre . '||' . $normAuteur . '||' . implode('|', $normIngredients);
    return hash('sha256', $payload);
}

function build_dedup_analysis(PDO $pdo): array
{
    $recipes = $pdo->query('SELECT id, titre, auteur, dedup_hash FROM recettes ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

    $firstIngredients = [];
    $ingRows = $pdo->query('SELECT recette_id, texte FROM ingredients ORDER BY recette_id ASC, ordre ASC')->fetchAll(PDO::FETCH_ASSOC);
    foreach ($ingRows as $row) {
        $recipeId = (int) $row['recette_id'];
        if (!isset($firstIngredients[$recipeId])) {
            $firstIngredients[$recipeId] = [];
        }
        if (count($firstIngredients[$recipeId]) < 3) {
            $firstIngredients[$recipeId][] = (string) $row['texte'];
        }
    }

    $signatures = [];
    $groups = [];
    $missingHashCount = 0;

    foreach ($recipes as $recipe) {
        $id = (int) $recipe['id'];
        $hash = compute_dedup_hash(
            (string) ($recipe['titre'] ?? ''),
            isset($recipe['auteur']) ? (string) $recipe['auteur'] : null,
            $firstIngredients[$id] ?? []
        );

        if (empty($recipe['dedup_hash'])) {
            $missingHashCount++;
        }

        $signatures[] = [
            'id' => $id,
            'hash' => $hash,
            'titre' => (string) ($recipe['titre'] ?? ''),
            'auteur' => (string) ($recipe['auteur'] ?? ''),
            'stored_hash' => (string) ($recipe['dedup_hash'] ?? ''),
        ];

        $groups[$hash][] = [
            'id' => $id,
            'titre' => (string) ($recipe['titre'] ?? ''),
            'auteur' => (string) ($recipe['auteur'] ?? ''),
        ];
    }

    $duplicateGroups = array_values(array_filter(
        $groups,
        static fn(array $group): bool => count($group) > 1
    ));

    usort($duplicateGroups, static fn(array $a, array $b): int => count($b) <=> count($a));

    return [
        'signatures' => $signatures,
        'duplicate_groups' => $duplicateGroups,
        'missing_hash_count' => $missingHashCount,
    ];
}

function scalar_int(PDO $pdo, string $sql): int
{
    $value = $pdo->query($sql)->fetchColumn();
    return (int) ($value ?: 0);
}

function table_exists(PDO $pdo, string $tableName): bool
{
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = :table
    ');
    $stmt->execute([':table' => $tableName]);
    return ((int) $stmt->fetchColumn()) > 0;
}

function ensure_users_role_column(PDO $pdo): void
{
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    $columnType = (string) ($column['Type'] ?? '');

    if (str_contains($columnType, "'contributeur'")) {
        return;
    }

    $pdo->exec("
        ALTER TABLE users
        MODIFY role ENUM('admin','contributeur','lecteur') NOT NULL DEFAULT 'lecteur'
    ");
}

function group_counts(PDO $pdo, string $sql): array
{
    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function ensure_password_resets_table(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS password_resets (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            token CHAR(64) NOT NULL,
            expires_at DATETIME NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
}

function issue_password_reset_token(PDO $pdo, int $userId, int $ttlHours = 2): string
{
    ensure_password_resets_table($pdo);

    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = date('Y-m-d H:i:s', strtotime('+' . $ttlHours . ' hours'));

    $pdo->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);

    $stmt = $pdo->prepare('
        INSERT INTO password_resets (user_id, token, expires_at)
        VALUES (?, ?, ?)
    ');
    $stmt->execute([$userId, $tokenHash, $expiresAt]);

    return $token;
}

ensure_users_role_column($pdo);

function resolve_openai_api_key_settings(): ?string
{
    $envFile = dirname(__DIR__, 2) . '/config/env.php';
    if (is_file($envFile)) {
        require_once $envFile;
    }

    $key = getenv('OPENAI_API_KEY') ?: ($_ENV['OPENAI_API_KEY'] ?? null);
    if (is_string($key) && trim($key) !== '') {
        return trim($key);
    }

    $legacy = getenv('OPENAI_KEY') ?: ($_ENV['OPENAI_KEY'] ?? null);
    if (is_string($legacy) && trim($legacy) !== '') {
        return trim($legacy);
    }

    // Fallback: config/openai.php (non versionné)
    $openAiConfig = dirname(__DIR__, 2) . '/config/openai.php';
    if (is_file($openAiConfig)) {
        $cfg = require $openAiConfig;
        if (is_array($cfg)) {
            $fileKey = $cfg['api_key'] ?? $cfg['API_KEY'] ?? null;
            if (is_string($fileKey) && trim($fileKey) !== '') {
                return trim($fileKey);
            }
        }
    }

    return null;
}

function fetch_openai_credit_summary(?string $apiKey): array
{
    if ($apiKey === null || $apiKey === '') {
        return ['ok' => false, 'error' => 'OPENAI_API_KEY absente'];
    }
    if (!function_exists('curl_init')) {
        return ['ok' => false, 'error' => 'Extension CURL absente'];
    }

    $headers = [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json',
    ];
    $orgId = getenv('OPENAI_ORG_ID') ?: ($_ENV['OPENAI_ORG_ID'] ?? '');
    if (is_string($orgId) && trim($orgId) !== '') {
        $headers[] = 'OpenAI-Organization: ' . trim($orgId);
    }
    $projectId = getenv('OPENAI_PROJECT_ID') ?: ($_ENV['OPENAI_PROJECT_ID'] ?? '');
    if (is_string($projectId) && trim($projectId) !== '') {
        $headers[] = 'OpenAI-Project: ' . trim($projectId);
    }

    $ch = curl_init('https://api.openai.com/v1/dashboard/billing/credit_grants');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 20,
    ]);
    $response = curl_exec($ch);
    if ($response === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return ['ok' => false, 'error' => 'Erreur CURL: ' . $err];
    }
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        $raw = (string) $response;
        $decoded = json_decode($raw, true);
        $msg = (string) ($decoded['error']['message'] ?? '');

        if (
            $httpCode === 403
            && (
                stripos($msg, 'only available through a browser') !== false
                || stripos($raw, 'only available through a browser') !== false
            )
        ) {
            return [
                'ok' => false,
                'error' => "Indisponible via clé API serveur. Consulte le dashboard OpenAI (Billing/Usage).",
            ];
        }

        $short = mb_substr($raw, 0, 180);
        return ['ok' => false, 'error' => 'HTTP ' . $httpCode . ' - ' . $short];
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        return ['ok' => false, 'error' => 'Réponse billing invalide'];
    }

    $totalGranted = isset($data['total_granted']) ? (float) $data['total_granted'] : null;
    $totalUsed = isset($data['total_used']) ? (float) $data['total_used'] : null;
    $totalAvailable = isset($data['total_available']) ? (float) $data['total_available'] : null;

    if ($totalAvailable === null && isset($data['grants']['data']) && is_array($data['grants']['data'])) {
        $granted = 0.0;
        $used = 0.0;
        foreach ($data['grants']['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $granted += (float) ($row['grant_amount'] ?? 0);
            $used += (float) ($row['used_amount'] ?? 0);
        }
        $totalGranted = $granted;
        $totalUsed = $used;
        $totalAvailable = $granted - $used;
    }

    if ($totalAvailable === null) {
        return ['ok' => false, 'error' => 'Crédit non disponible pour cette clé/API'];
    }

    return [
        'ok' => true,
        'granted' => (float) ($totalGranted ?? 0),
        'used' => (float) ($totalUsed ?? 0),
        'available' => (float) $totalAvailable,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $protectedAdminEmail = 'johny.benisvy@gmail.com';

    if ($action === 'update_user_role') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $role = (string) ($_POST['role'] ?? '');
        if ($userId > 0 && in_array($role, ['lecteur', 'contributeur', 'admin'], true)) {
            ensure_users_role_column($pdo);

            $stmtUser = $pdo->prepare('SELECT email FROM users WHERE id = :id LIMIT 1');
            $stmtUser->execute([':id' => $userId]);
            $email = mb_strtolower(trim((string) ($stmtUser->fetchColumn() ?: '')));

            // Compte admin protégé: rôle forcé à admin.
            if ($email === $protectedAdminEmail) {
                $role = 'admin';
            }

            $stmt = $pdo->prepare('UPDATE users SET role = :role WHERE id = :id');
            $stmt->execute([
                ':role' => $role,
                ':id' => $userId,
            ]);

            $stmtVerify = $pdo->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $stmtVerify->execute([':id' => $userId]);
            $storedRole = (string) ($stmtVerify->fetchColumn() ?: '');

            if ($storedRole !== $role) {
                header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_role_update_failed');
                exit;
            }
        }
        header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=users');
        exit;
    }

    if ($action === 'set_temporary_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $temporaryPassword = (string) ($_POST['temporary_password'] ?? '');

        if ($userId <= 0) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_temp_password_invalid');
            exit;
        }

        if (strlen($temporaryPassword) < 8) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_temp_password_too_short');
            exit;
        }

        $stmtUser = $pdo->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
        $stmtUser->execute([':id' => $userId]);
        $userExists = $stmtUser->fetchColumn();

        if (!$userExists) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_temp_password_not_found');
            exit;
        }

        $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
        $stmt->execute([
            ':hash' => $hash,
            ':id' => $userId,
        ]);

        if (table_exists($pdo, 'password_resets')) {
            $stmtDeletePasswordResets = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
            $stmtDeletePasswordResets->execute([':user_id' => $userId]);
        }

        header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=user_temp_password');
        exit;
    }

    if ($action === 'delete_user') {
        $userId = (int) ($_POST['user_id'] ?? 0);
        $currentUserId = (int) ($_SESSION['user']['id'] ?? 0);

        if ($userId <= 0) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_delete_invalid');
            exit;
        }
        if ($userId === $currentUserId) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_delete_self');
            exit;
        }

        $stmtUser = $pdo->prepare('SELECT id, nom, email FROM users WHERE id = :id LIMIT 1');
        $stmtUser->execute([':id' => $userId]);
        $userToDelete = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userToDelete) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_delete_not_found');
            exit;
        }

        $emailToDelete = mb_strtolower(trim((string) ($userToDelete['email'] ?? '')));
        if ($emailToDelete === $protectedAdminEmail) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_delete_protected');
            exit;
        }

        $stmtTarget = $pdo->prepare('SELECT id, nom, email FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1');
        $stmtTarget->execute([':email' => $protectedAdminEmail]);
        $targetOwner = $stmtTarget->fetch(PDO::FETCH_ASSOC);

        if (!$targetOwner) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_delete_target_missing');
            exit;
        }

        $sourceAuthor = trim((string) ($userToDelete['nom'] ?? ''));
        $targetAuthor = trim((string) ($targetOwner['nom'] ?? ''));
        if ($targetAuthor === '') {
            $targetAuthor = $protectedAdminEmail;
        }

        $movedRecipes = 0;
        $deletedFavoris = 0;
        $deletedSelections = 0;

        $pdo->beginTransaction();
        try {
            if ($sourceAuthor !== '') {
                $stmtMove = $pdo->prepare('UPDATE recettes SET auteur = :target WHERE TRIM(COALESCE(auteur, "")) = :source');
                $stmtMove->execute([
                    ':target' => $targetAuthor,
                    ':source' => $sourceAuthor,
                ]);
                $movedRecipes = $stmtMove->rowCount();
            }

            $stmtDeleteFavoris = $pdo->prepare('DELETE FROM user_favoris WHERE user_id = :user_id');
            $stmtDeleteFavoris->execute([':user_id' => $userId]);
            $deletedFavoris = $stmtDeleteFavoris->rowCount();

            $stmtDeleteSelections = $pdo->prepare('DELETE FROM user_recette_selection WHERE user_id = :user_id');
            $stmtDeleteSelections->execute([':user_id' => $userId]);
            $deletedSelections = $stmtDeleteSelections->rowCount();

            if (table_exists($pdo, 'password_resets')) {
                $stmtDeletePasswordResets = $pdo->prepare('DELETE FROM password_resets WHERE user_id = :user_id');
                $stmtDeletePasswordResets->execute([':user_id' => $userId]);
            }

            $stmtDeleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $stmtDeleteUser->execute([':id' => $userId]);

            if ($stmtDeleteUser->rowCount() !== 1) {
                throw new RuntimeException('Suppression utilisateur non appliquée');
            }

            $pdo->commit();
            header(
                'Location: ' . PUBLIC_URL
                . '/admin/settings.php?saved=user_deleted'
                . '&moved=' . $movedRecipes
                . '&favoris=' . $deletedFavoris
                . '&selection=' . $deletedSelections
            );
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_delete_failed');
            exit;
        }
    }

    if ($action === 'reset_user_password') {
        $userId = (int) ($_POST['user_id'] ?? 0);

        if ($userId <= 0) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_password_reset_invalid');
            exit;
        }

        $stmtUser = $pdo->prepare('SELECT id, nom, email FROM users WHERE id = :id LIMIT 1');
        $stmtUser->execute([':id' => $userId]);
        $userToReset = $stmtUser->fetch(PDO::FETCH_ASSOC);

        if (!$userToReset) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_password_reset_not_found');
            exit;
        }

        $email = trim((string) ($userToReset['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_password_reset_email');
            exit;
        }

        $token = issue_password_reset_token($pdo, (int) $userToReset['id']);
        $resetLink = app_absolute_url('?action=reset_password&token=' . urlencode($token));
        $nom = htmlspecialchars((string) ($userToReset['nom'] ?? ''), ENT_QUOTES, 'UTF-8');

        $subject = 'Réinitialisation de votre mot de passe';
        $html = "
            <p>Bonjour {$nom},</p>
            <p>Un administrateur a demandé la réinitialisation de votre mot de passe sur Mémoire de Saveurs.</p>
            <p>Pour choisir un nouveau mot de passe, cliquez sur ce lien :</p>
            <p><a href=\"{$resetLink}\">Réinitialiser mon mot de passe</a></p>
            <p>Ce lien expire dans 2 heures.</p>
        ";

        $sent = MailService::send($email, $subject, $html);
        if (!$sent) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=user_password_reset_mail');
            exit;
        }

        header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=user_password_reset');
        exit;
    }

    if ($action === 'save_options') {
        $categories = normalize_key_label_pairs(
            (array) ($_POST['categories_keys'] ?? []),
            (array) ($_POST['categories_labels'] ?? [])
        );
        $typesCuisson = normalize_key_label_pairs(
            (array) ($_POST['types_cuisson_keys'] ?? []),
            (array) ($_POST['types_cuisson_labels'] ?? [])
        );
        $typesRecette = normalize_key_label_pairs(
            (array) ($_POST['types_recette_keys'] ?? []),
            (array) ($_POST['types_recette_labels'] ?? [])
        );

        if (empty($categories) || empty($typesCuisson) || empty($typesRecette)) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=options_required');
            exit;
        }

        $newOptions = [
            'categories' => $categories,
            'types_cuisson' => $typesCuisson,
            'types_recette' => $typesRecette,
        ];

        $tmpPath = $configPath . '.tmp';
        $payload = export_options_php($newOptions);
        $written = file_put_contents($tmpPath, $payload, LOCK_EX);

        if ($written === false || !rename($tmpPath, $configPath)) {
            @unlink($tmpPath);
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=write_failed');
            exit;
        }

        header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=options');
        exit;
    }

    if ($action === 'rebuild_dedup_hash') {
        $analysis = build_dedup_analysis($pdo);
        $updated = 0;
        $duplicates = 0;
        $seen = [];

        $pdo->beginTransaction();
        try {
            $pdo->exec('UPDATE recettes SET dedup_hash = NULL');
            $stmt = $pdo->prepare('UPDATE recettes SET dedup_hash = :hash WHERE id = :id');

            foreach ($analysis['signatures'] as $signature) {
                $hash = (string) ($signature['hash'] ?? '');
                if ($hash === '') {
                    continue;
                }
                if (isset($seen[$hash])) {
                    $duplicates++;
                    continue;
                }

                $stmt->execute([
                    ':hash' => $hash,
                    ':id' => (int) $signature['id'],
                ]);
                $seen[$hash] = true;
                $updated++;
            }

            $pdo->commit();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=dedup&updated=' . $updated . '&duplicates=' . $duplicates);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=dedup_failed');
            exit;
        }
    }

    if ($action === 'normalize_categories') {
        $categoryIndex = build_category_index($currentOptions);
        $rows = $pdo->query('SELECT id, categorie FROM recettes')->fetchAll(PDO::FETCH_ASSOC);

        $updated = 0;
        $unknown = [];

        $stmt = $pdo->prepare('UPDATE recettes SET categorie = :categorie WHERE id = :id');

        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $raw = trim((string) ($row['categorie'] ?? ''));
                if ($raw === '') {
                    continue;
                }

                $canonical = canonical_text_key($raw);
                $target = $categoryIndex[$canonical] ?? null;

                if ($target === null) {
                    $unknown[$canonical] = true;
                    continue;
                }

                if ($target !== $raw) {
                    $stmt->execute([
                        ':categorie' => $target,
                        ':id' => (int) $row['id'],
                    ]);
                    $updated++;
                }
            }
            $pdo->commit();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=categories&updated=' . $updated . '&unknown=' . count($unknown));
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=categories_failed');
            exit;
        }
    }

    if ($action === 'merge_tag') {
        $sourceId = (int) ($_POST['source_tag_id'] ?? 0);
        $targetId = (int) ($_POST['target_tag_id'] ?? 0);
        if ($sourceId <= 0 || $targetId <= 0 || $sourceId === $targetId) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=tag_merge_invalid');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $countStmt = $pdo->prepare('SELECT COUNT(*) FROM recette_tags WHERE tag_id = :id');
            $countStmt->execute([':id' => $sourceId]);
            $sourceUsage = (int) $countStmt->fetchColumn();

            $ins = $pdo->prepare('INSERT IGNORE INTO recette_tags (recette_id, tag_id) SELECT recette_id, :target FROM recette_tags WHERE tag_id = :source');
            $ins->execute([':target' => $targetId, ':source' => $sourceId]);

            $delLinks = $pdo->prepare('DELETE FROM recette_tags WHERE tag_id = :id');
            $delLinks->execute([':id' => $sourceId]);

            $delTag = $pdo->prepare('DELETE FROM tags WHERE id = :id');
            $delTag->execute([':id' => $sourceId]);

            $pdo->commit();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=tag_merge&moved=' . $sourceUsage);
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=tag_merge_failed');
            exit;
        }
    }

    if ($action === 'delete_tag') {
        $tagId = (int) ($_POST['tag_id'] ?? 0);
        if ($tagId <= 0) {
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=tag_delete_invalid');
            exit;
        }

        $pdo->beginTransaction();
        try {
            $delLinks = $pdo->prepare('DELETE FROM recette_tags WHERE tag_id = :id');
            $delLinks->execute([':id' => $tagId]);

            $delTag = $pdo->prepare('DELETE FROM tags WHERE id = :id');
            $delTag->execute([':id' => $tagId]);

            $pdo->commit();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?saved=tag_delete');
            exit;
        } catch (Throwable $e) {
            $pdo->rollBack();
            header('Location: ' . PUBLIC_URL . '/admin/settings.php?error=tag_delete_failed');
            exit;
        }
    }
}

$protectedAdminEmail = 'johny.benisvy@gmail.com';
$users = $pdo->query('SELECT id, nom, email, role FROM users ORDER BY nom')->fetchAll(PDO::FETCH_ASSOC);

$kpis = [
    'total_recettes' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes'),
    'photos_total' => scalar_int($pdo, 'SELECT COUNT(*) FROM photos_recettes'),
    'photos_ia_total' => scalar_int($pdo, "SELECT COUNT(*) FROM photos_recettes WHERE fichier LIKE 'recette_ai_%'"),
    'sans_image' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes r WHERE NOT EXISTS (SELECT 1 FROM photos_recettes p WHERE p.recette_id = r.id)'),
    'sans_categorie' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes WHERE categorie IS NULL OR TRIM(categorie) = ""'),
    'sans_source' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes WHERE source IS NULL OR TRIM(source) = ""'),
    'sans_type_cuisson' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes WHERE type_cuisson IS NULL OR TRIM(type_cuisson) = ""'),
    'sans_ingredients' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes r WHERE NOT EXISTS (SELECT 1 FROM ingredients i WHERE i.recette_id = r.id)'),
    'sans_etapes' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes r WHERE NOT EXISTS (SELECT 1 FROM etapes e WHERE e.recette_id = r.id)'),
    'favoris_total' => scalar_int($pdo, 'SELECT COUNT(*) FROM user_favoris'),
    'selection_total' => scalar_int($pdo, 'SELECT COUNT(*) FROM user_recette_selection'),
    'dedup_hash_vides' => scalar_int($pdo, 'SELECT COUNT(*) FROM recettes WHERE dedup_hash IS NULL OR TRIM(dedup_hash) = ""'),
];
$kpis['incompletes'] = $kpis['sans_ingredients'] + $kpis['sans_etapes'];

$openAiCredit = fetch_openai_credit_summary(resolve_openai_api_key_settings());

$byAuthor = group_counts($pdo, "
    SELECT COALESCE(NULLIF(TRIM(auteur), ''), '—') AS label, COUNT(*) AS total
    FROM recettes
    GROUP BY label
    ORDER BY total DESC, label ASC
");
$byCategory = group_counts($pdo, "
    SELECT COALESCE(NULLIF(TRIM(categorie), ''), '—') AS label, COUNT(*) AS total
    FROM recettes
    GROUP BY label
    ORDER BY total DESC, label ASC
");
$byTypeCuisson = group_counts($pdo, "
    SELECT COALESCE(NULLIF(TRIM(type_cuisson), ''), '—') AS label, COUNT(*) AS total
    FROM recettes
    GROUP BY label
    ORDER BY total DESC, label ASC
");
$byTypeRecette = group_counts($pdo, "
    SELECT COALESCE(NULLIF(TRIM(type_recette), ''), '—') AS label, COUNT(*) AS total
    FROM recettes
    GROUP BY label
    ORDER BY total DESC, label ASC
");

$topFavoris = group_counts($pdo, "
    SELECT r.id, r.titre, COUNT(*) AS total
    FROM user_favoris uf
    INNER JOIN recettes r ON r.id = uf.recette_id
    GROUP BY r.id, r.titre
    ORDER BY total DESC, r.titre ASC
    LIMIT 10
");

$tags = group_counts($pdo, "
    SELECT t.id, t.nom, COUNT(rt.recette_id) AS usage_count
    FROM tags t
    LEFT JOIN recette_tags rt ON rt.tag_id = t.id
    GROUP BY t.id, t.nom
    ORDER BY t.nom ASC
");

$dedupAnalysis = build_dedup_analysis($pdo);
$duplicateGroups = array_slice($dedupAnalysis['duplicate_groups'], 0, 10);
$listBaseUrl = PUBLIC_URL . '/index.php';
$dashboardLinks = [
    'total_recettes' => $listBaseUrl,
    'sans_image' => $listBaseUrl . '?dashboard_filter=sans_image',
    'photos_ia_total' => $listBaseUrl . '?dashboard_filter=photos_ia',
    'sans_categorie' => $listBaseUrl . '?dashboard_filter=sans_categorie',
    'sans_source' => $listBaseUrl . '?dashboard_filter=sans_source',
    'sans_type_cuisson' => $listBaseUrl . '?dashboard_filter=sans_type_cuisson',
    'incompletes' => $listBaseUrl . '?dashboard_filter=incompletes',
    'favoris_total' => $listBaseUrl . '?favoris=1',
    'selection_total' => $listBaseUrl . '?selection=1',
    'dedup_hash_vides' => $listBaseUrl . '?dashboard_filter=dedup_hash_vides',
    'groupes_doublons' => $listBaseUrl . '?dashboard_filter=doublons',
];

/**
 * Construit un lien vers la liste filtrée à partir d'un libellé de répartition.
 */
function build_group_filter_link(string $listBaseUrl, string $dimension, string $label): ?string
{
    $label = trim($label);
    if ($label === '' || $label === '—') {
        return null;
    }

    return match ($dimension) {
        'auteur' => $listBaseUrl . '?auteur=' . rawurlencode($label),
        'categorie' => $listBaseUrl . '?categorie=' . rawurlencode($label),
        'type_cuisson' => $listBaseUrl . '?type_cuisson=' . rawurlencode($label),
        'type_recette' => $listBaseUrl . '?type_recette=' . rawurlencode($label),
        default => null,
    };
}

$bodyClass = 'page-admin-settings';
$page = 'admin-settings';
$pageTitle = 'Paramètres';
$useBootstrap = true;

require __DIR__ . '/../ui/layout_start.php';
?>

<div class="page settings-page container-xl">
  <?php if (($_GET['saved'] ?? '') === 'users'): ?>
    <div class="alert alert-success">Rôle utilisateur mis à jour.</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'user_deleted'): ?>
    <div class="alert alert-success">
      Utilisateur supprimé. Recettes transférées: <?= (int) ($_GET['moved'] ?? 0) ?>,
      favoris supprimés: <?= (int) ($_GET['favoris'] ?? 0) ?>,
      sélections supprimées: <?= (int) ($_GET['selection'] ?? 0) ?>.
    </div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'user_password_reset'): ?>
    <div class="alert alert-success">Email de réinitialisation du mot de passe envoyé.</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'user_temp_password'): ?>
    <div class="alert alert-success">Mot de passe temporaire enregistré pour l’utilisateur.</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'options'): ?>
    <div class="alert alert-success">Options recettes enregistrées.</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'dedup'): ?>
    <div class="alert alert-success">Recalcul `dedup_hash` terminé (<?= (int) ($_GET['updated'] ?? 0) ?> mis à jour, <?= (int) ($_GET['duplicates'] ?? 0) ?> doublons ignorés).</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'categories'): ?>
    <div class="alert alert-success">Normalisation catégories terminée (<?= (int) ($_GET['updated'] ?? 0) ?> recette(s) corrigée(s), <?= (int) ($_GET['unknown'] ?? 0) ?> catégorie(s) inconnue(s)).</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'tag_merge'): ?>
    <div class="alert alert-success">Fusion de tag effectuée (<?= (int) ($_GET['moved'] ?? 0) ?> liaison(s) traitée(s)).</div>
  <?php endif; ?>
  <?php if (($_GET['saved'] ?? '') === 'tag_delete'): ?>
    <div class="alert alert-success">Tag supprimé.</div>
  <?php endif; ?>

  <?php if (($_GET['error'] ?? '') === 'options_required'): ?>
    <div class="alert alert-error">Chaque liste doit contenir au moins une valeur valide.</div>
  <?php endif; ?>
  <?php if (($_GET['error'] ?? '') === 'write_failed'): ?>
    <div class="alert alert-error">Impossible d'écrire le fichier `config/recette_options.php`.</div>
  <?php endif; ?>
  <?php if (str_starts_with((string) ($_GET['error'] ?? ''), 'dedup_')): ?>
    <div class="alert alert-error">Erreur pendant le recalcul des hashes de déduplication.</div>
  <?php endif; ?>
  <?php if (str_starts_with((string) ($_GET['error'] ?? ''), 'categories_')): ?>
    <div class="alert alert-error">Erreur pendant la normalisation des catégories.</div>
  <?php endif; ?>
  <?php if (str_starts_with((string) ($_GET['error'] ?? ''), 'tag_')): ?>
    <div class="alert alert-error">Erreur sur l'action de gestion des tags.</div>
  <?php endif; ?>
  <?php if (str_starts_with((string) ($_GET['error'] ?? ''), 'user_delete_')): ?>
    <div class="alert alert-error">
      <?php
      $userDeleteError = (string) ($_GET['error'] ?? '');
      if ($userDeleteError === 'user_delete_protected') {
          echo 'Le compte admin johny.benisvy@gmail.com est protégé et ne peut pas être supprimé.';
      } elseif ($userDeleteError === 'user_delete_self') {
          echo 'Vous ne pouvez pas supprimer votre propre compte connecté.';
      } elseif ($userDeleteError === 'user_delete_target_missing') {
          echo 'Compte cible johny.benisvy@gmail.com introuvable pour le transfert des recettes.';
      } elseif ($userDeleteError === 'user_delete_not_found') {
          echo 'Utilisateur introuvable.';
      } else {
          echo 'Erreur pendant la suppression de l’utilisateur.';
      }
      ?>
    </div>
  <?php endif; ?>
  <?php if (($_GET['error'] ?? '') === 'user_role_update_failed'): ?>
    <div class="alert alert-error">Le rôle n’a pas pu être enregistré en base. La colonne `users.role` a probablement été corrigée, merci de réessayer une fois.</div>
  <?php endif; ?>
  <?php if (str_starts_with((string) ($_GET['error'] ?? ''), 'user_password_reset_')): ?>
    <div class="alert alert-error">
      <?php
      $userPasswordResetError = (string) ($_GET['error'] ?? '');
      if ($userPasswordResetError === 'user_password_reset_not_found') {
          echo 'Utilisateur introuvable.';
      } elseif ($userPasswordResetError === 'user_password_reset_email') {
          echo 'Adresse email utilisateur invalide.';
      } elseif ($userPasswordResetError === 'user_password_reset_mail') {
          echo 'Impossible d’envoyer l’email de réinitialisation.';
      } else {
          echo 'Erreur pendant la demande de réinitialisation du mot de passe.';
      }
      ?>
    </div>
  <?php endif; ?>
  <?php if (str_starts_with((string) ($_GET['error'] ?? ''), 'user_temp_password_')): ?>
    <div class="alert alert-error">
      <?php
      $userTempPasswordError = (string) ($_GET['error'] ?? '');
      if ($userTempPasswordError === 'user_temp_password_not_found') {
          echo 'Utilisateur introuvable.';
      } elseif ($userTempPasswordError === 'user_temp_password_too_short') {
          echo 'Le mot de passe temporaire doit contenir au moins 8 caractères.';
      } else {
          echo 'Erreur pendant la définition du mot de passe temporaire.';
      }
      ?>
    </div>
  <?php endif; ?>

  <section class="settings-card card border-0 shadow-sm rounded-4 overflow-hidden">
    <div class="p-4 p-md-5" style="background: linear-gradient(135deg, rgba(31,70,56,0.95), rgba(44,92,74,0.9)); color:#fff;">
      <h1 class="page-title mb-2" style="color:#fff;">Paramètres</h1>
      <p class="mb-0" style="color:rgba(255,255,255,0.9);">Dashboard, administration des utilisateurs et maintenance de l'application.</p>
    </div>
  </section>

  <section class="settings-card card border-0 shadow-sm rounded-4">
    <div class="d-flex flex-wrap gap-2">
      <a class="btn btn-secondary btn-small" href="#dashboard">Dashboard</a>
      <a class="btn btn-secondary btn-small" href="#repartition">Répartition</a>
      <a class="btn btn-secondary btn-small" href="#maintenance">Maintenance</a>
      <a class="btn btn-secondary btn-small" href="#tags">Tags</a>
      <a class="btn btn-secondary btn-small" href="#users">Utilisateurs</a>
      <a class="btn btn-secondary btn-small" href="#options">Options</a>
    </div>
  </section>

  <section id="dashboard" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Dashboard</h2>
    <div class="row g-3">
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['total_recettes']) ?>"><span>Total recettes</span><strong><?= (int) $kpis['total_recettes'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><span class="stat-card"><span>Total photos</span><strong><?= (int) $kpis['photos_total'] ?></strong></span></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['photos_ia_total']) ?>"><span>Photos IA</span><strong><?= (int) $kpis['photos_ia_total'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['sans_image']) ?>"><span>Sans image</span><strong><?= (int) $kpis['sans_image'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['sans_categorie']) ?>"><span>Sans catégorie</span><strong><?= (int) $kpis['sans_categorie'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['sans_source']) ?>"><span>Sans source</span><strong><?= (int) $kpis['sans_source'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['sans_type_cuisson']) ?>"><span>Sans type cuisson</span><strong><?= (int) $kpis['sans_type_cuisson'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['incompletes']) ?>"><span>Incomplètes</span><strong><?= (int) $kpis['incompletes'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['favoris_total']) ?>"><span>Favoris total</span><strong><?= (int) $kpis['favoris_total'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['selection_total']) ?>"><span>Sélections total</span><strong><?= (int) $kpis['selection_total'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['dedup_hash_vides']) ?>"><span>dedup_hash vides</span><strong><?= (int) $kpis['dedup_hash_vides'] ?></strong></a></div>
      <div class="col-6 col-md-4 col-xl"><a class="stat-card stat-card-link" href="<?= htmlspecialchars($dashboardLinks['groupes_doublons']) ?>"><span>Groupes doublons</span><strong><?= count($dedupAnalysis['duplicate_groups']) ?></strong></a></div>
    </div>
  </section>

  <section class="settings-card card border-0 shadow-sm rounded-4">
    <h2>OpenAI</h2>
    <table class="recettes-table compact-table">
      <tbody>
        <tr>
          <td>Crédit OpenAI restant</td>
          <td>
            <?php if (!empty($openAiCredit['ok'])): ?>
              <strong><?= number_format((float) $openAiCredit['available'], 2, ',', ' ') ?> $</strong>
            <?php else: ?>
              <span class="muted">Indisponible (<?= htmlspecialchars((string) ($openAiCredit['error'] ?? 'erreur inconnue')) ?>)</span>
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td>Crédit consommé</td>
          <td>
            <?php if (!empty($openAiCredit['ok'])): ?>
              <?= number_format((float) ($openAiCredit['used'] ?? 0), 2, ',', ' ') ?> $
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td>Crédit alloué</td>
          <td>
            <?php if (!empty($openAiCredit['ok'])): ?>
              <?= number_format((float) ($openAiCredit['granted'] ?? 0), 2, ',', ' ') ?> $
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
        </tr>
        <tr>
          <td>Recettes dans l'application</td>
          <td><?= (int) $kpis['total_recettes'] ?></td>
        </tr>
        <tr>
          <td>Photos dans l'application</td>
          <td><?= (int) $kpis['photos_total'] ?></td>
        </tr>
        <tr>
          <td>Photos générées IA (estimé)</td>
          <td><?= (int) $kpis['photos_ia_total'] ?></td>
        </tr>
      </tbody>
    </table>
    <p class="muted" style="margin-top:8px;">
      Note: le crédit OpenAI dépend des permissions de clé API et peut être indisponible selon le type de compte.
    </p>
    <p style="margin-top:8px;">
      <a href="https://platform.openai.com/settings/organization/billing/overview" target="_blank" rel="noopener noreferrer">
        Ouvrir le dashboard OpenAI Billing/Usage
      </a>
    </p>
  </section>

  <section id="repartition" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Répartition</h2>
    <div class="settings-grid settings-grid-2">
      <div class="settings-group">
        <h3>Par auteur</h3>
        <table class="recettes-table compact-table">
          <tbody>
            <?php foreach ($byAuthor as $row): ?>
              <?php $link = build_group_filter_link($listBaseUrl, 'auteur', (string) $row['label']); ?>
              <tr
                <?= $link ? 'class="settings-click-row" data-href="' . htmlspecialchars($link) . '" tabindex="0" role="link"' : '' ?>
              >
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= htmlspecialchars((string) $row['label']) ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars((string) $row['label']) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= (int) $row['total'] ?></a>
                  <?php else: ?>
                    <?= (int) $row['total'] ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="settings-group">
        <h3>Par catégorie</h3>
        <table class="recettes-table compact-table">
          <tbody>
            <?php foreach ($byCategory as $row): ?>
              <?php $link = build_group_filter_link($listBaseUrl, 'categorie', (string) $row['label']); ?>
              <tr
                <?= $link ? 'class="settings-click-row" data-href="' . htmlspecialchars($link) . '" tabindex="0" role="link"' : '' ?>
              >
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= htmlspecialchars((string) $row['label']) ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars((string) $row['label']) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= (int) $row['total'] ?></a>
                  <?php else: ?>
                    <?= (int) $row['total'] ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="settings-group">
        <h3>Par type cuisson</h3>
        <table class="recettes-table compact-table">
          <tbody>
            <?php foreach ($byTypeCuisson as $row): ?>
              <?php $link = build_group_filter_link($listBaseUrl, 'type_cuisson', (string) $row['label']); ?>
              <tr
                <?= $link ? 'class="settings-click-row" data-href="' . htmlspecialchars($link) . '" tabindex="0" role="link"' : '' ?>
              >
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= htmlspecialchars((string) $row['label']) ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars((string) $row['label']) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= (int) $row['total'] ?></a>
                  <?php else: ?>
                    <?= (int) $row['total'] ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="settings-group">
        <h3>Par type recette</h3>
        <table class="recettes-table compact-table">
          <tbody>
            <?php foreach ($byTypeRecette as $row): ?>
              <?php $link = build_group_filter_link($listBaseUrl, 'type_recette', (string) $row['label']); ?>
              <tr
                <?= $link ? 'class="settings-click-row" data-href="' . htmlspecialchars($link) . '" tabindex="0" role="link"' : '' ?>
              >
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= htmlspecialchars((string) $row['label']) ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars((string) $row['label']) ?>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if ($link): ?>
                    <a href="<?= htmlspecialchars($link) ?>"><?= (int) $row['total'] ?></a>
                  <?php else: ?>
                    <?= (int) $row['total'] ?>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Suivi usage</h2>
    <table class="recettes-table compact-table">
      <thead><tr><th>Recette</th><th>Nb favoris</th></tr></thead>
      <tbody>
        <?php if (empty($topFavoris)): ?>
          <tr><td colspan="2" class="muted">Aucun favori enregistré.</td></tr>
        <?php else: ?>
          <?php foreach ($topFavoris as $row): ?>
            <tr>
              <td><a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int) $row['id'] ?>" target="_blank"><?= htmlspecialchars((string) $row['titre']) ?></a></td>
              <td><?= (int) $row['total'] ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <section class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Doublons potentiels</h2>
    <?php if (empty($duplicateGroups)): ?>
      <p class="muted">Aucun groupe détecté avec la signature (titre + auteur + 3 premiers ingrédients).</p>
    <?php else: ?>
      <div class="settings-rows">
        <?php foreach ($duplicateGroups as $idx => $group): ?>
          <div class="dup-group">
            <strong>Groupe #<?= $idx + 1 ?> (<?= count($group) ?> recettes)</strong>
            <ul>
              <?php foreach ($group as $item): ?>
                <li>
                  <a href="<?= PUBLIC_URL ?>/recette.php?id=<?= (int) $item['id'] ?>" target="_blank">#<?= (int) $item['id'] ?> - <?= htmlspecialchars((string) $item['titre']) ?></a>
                  <?php if (!empty($item['auteur'])): ?> · <?= htmlspecialchars((string) $item['auteur']) ?><?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section id="maintenance" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Maintenance</h2>
    <div class="settings-actions settings-actions-start">
      <form method="post" onsubmit="return confirm('Lancer le recalcul des dedup_hash ?');">
        <input type="hidden" name="action" value="rebuild_dedup_hash">
        <button type="submit" class="btn btn-secondary">Recalculer dedup_hash</button>
      </form>
      <form method="post" onsubmit="return confirm('Normaliser les catégories selon recette_options.php ?');">
        <input type="hidden" name="action" value="normalize_categories">
        <button type="submit" class="btn btn-secondary">Normaliser les catégories</button>
      </form>
    </div>
  </section>

  <section id="tags" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Gestion Tags</h2>
    <div class="settings-grid settings-grid-2">
      <div class="settings-group">
        <h3>Fusionner un tag</h3>
        <form method="post" class="settings-form" onsubmit="return confirm('Fusionner ce tag dans la cible ?');">
          <input type="hidden" name="action" value="merge_tag">
          <label>Tag source</label>
          <select name="source_tag_id" required>
            <option value="">Choisir...</option>
            <?php foreach ($tags as $tag): ?>
              <option value="<?= (int) $tag['id'] ?>"><?= htmlspecialchars((string) $tag['nom']) ?> (<?= (int) $tag['usage_count'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <label>Tag cible</label>
          <select name="target_tag_id" required>
            <option value="">Choisir...</option>
            <?php foreach ($tags as $tag): ?>
              <option value="<?= (int) $tag['id'] ?>"><?= htmlspecialchars((string) $tag['nom']) ?> (<?= (int) $tag['usage_count'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-primary">Fusionner</button>
        </form>
      </div>

      <div class="settings-group">
        <h3>Supprimer un tag</h3>
        <form method="post" class="settings-form" onsubmit="return confirm('Supprimer ce tag et ses associations ?');">
          <input type="hidden" name="action" value="delete_tag">
          <label>Tag</label>
          <select name="tag_id" required>
            <option value="">Choisir...</option>
            <?php foreach ($tags as $tag): ?>
              <option value="<?= (int) $tag['id'] ?>"><?= htmlspecialchars((string) $tag['nom']) ?> (<?= (int) $tag['usage_count'] ?>)</option>
            <?php endforeach; ?>
          </select>
          <button type="submit" class="btn btn-danger">Supprimer</button>
        </form>
      </div>
    </div>
  </section>

  <section id="users" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Utilisateurs</h2>
    <div class="table-wrap">
      <table class="recettes-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Rôle</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <?php
              $isCurrentUser = ((int) $u['id'] === (int) ($_SESSION['user']['id'] ?? 0));
              $isProtectedUser = (mb_strtolower(trim((string) ($u['email'] ?? ''))) === $protectedAdminEmail);
            ?>
            <tr>
              <td><?= htmlspecialchars((string) $u['nom']) ?></td>
              <td><?= htmlspecialchars((string) $u['email']) ?></td>
              <td><?= htmlspecialchars((string) $u['role']) ?></td>
              <td>
                <?php if (!$isCurrentUser): ?>
                  <form method="post" class="settings-inline-form">
                    <input type="hidden" name="action" value="update_user_role">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <select name="role" <?= $isProtectedUser ? 'disabled' : '' ?>>
                      <option value="lecteur" <?= $u['role'] === 'lecteur' ? 'selected' : '' ?>>Lecteur</option>
                      <option value="contributeur" <?= $u['role'] === 'contributeur' ? 'selected' : '' ?>>Contributeur</option>
                      <option value="admin" <?= $u['role'] === 'admin' ? 'selected' : '' ?>>Admin</option>
                    </select>
                    <?php if ($isProtectedUser): ?>
                      <button class="btn btn-small btn-primary" type="button" disabled>Rôle verrouillé</button>
                    <?php else: ?>
                      <button class="btn btn-small btn-primary" type="submit">Mettre à jour</button>
                    <?php endif; ?>
                  </form>
                  <form method="post" class="settings-inline-form" onsubmit="return confirm('Envoyer un email de réinitialisation du mot de passe à cet utilisateur ?');">
                    <input type="hidden" name="action" value="reset_user_password">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <button class="btn btn-small btn-secondary" type="submit">Réinitialiser le mot de passe</button>
                  </form>
                  <form method="post" class="settings-inline-form" onsubmit="return confirm('Définir ce mot de passe temporaire pour cet utilisateur ?');">
                    <input type="hidden" name="action" value="set_temporary_password">
                    <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                    <input type="password" name="temporary_password" placeholder="Mot de passe temporaire" minlength="8" required>
                    <button class="btn btn-small btn-secondary" type="submit">Définir le mot de passe</button>
                  </form>
                  <?php if (!$isProtectedUser): ?>
                    <form method="post" class="settings-inline-form" onsubmit="return confirm('Supprimer cet utilisateur ? Ses recettes seront conservées et rattachées à johny.benisvy@gmail.com. Ses favoris et sélections seront supprimés.');">
                      <input type="hidden" name="action" value="delete_user">
                      <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                      <button class="btn btn-small btn-danger" type="submit">Supprimer</button>
                    </form>
                  <?php else: ?>
                    <span class="muted">Compte protégé</span>
                  <?php endif; ?>
                <?php else: ?>
                  —
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section id="options" class="settings-card card border-0 shadow-sm rounded-4">
    <h2>Options Recettes</h2>
    <p class="muted">Édite les listes utilisées par l'application (`config/recette_options.php`).</p>
    <div class="alert" style="margin-top:10px;">
      <strong>Aide rapide</strong><br>
      Clé: utilise des identifiants simples en minuscules (lettres/chiffres, `-` ou `_`), sans espace.<br>
      Libellé: texte affiché dans l’interface (avec accents/casse si besoin).<br>
      Exemples: clé `entree` → libellé `Entrée`, clé `micro-onde` → libellé `Micro-onde`, clé `base` → libellé `Base`.<br>
      Astuce: évite de renommer une clé déjà utilisée en base si tu veux conserver les filtres existants.
    </div>

    <form method="post" class="settings-form" data-settings-form>
      <input type="hidden" name="action" value="save_options">

      <div class="settings-grid">
        <div class="settings-group" data-group="categories">
          <div class="settings-group-head">
            <h3>Catégories</h3>
            <button type="button" class="btn btn-small btn-secondary" data-add-row="categories">+ Ajouter</button>
          </div>
          <div class="settings-rows" data-rows="categories">
            <?php foreach (($currentOptions['categories'] ?? []) as $key => $label): ?>
              <div class="settings-row">
                <input type="text" name="categories_keys[]" value="<?= htmlspecialchars((string) $key) ?>" placeholder="clé">
                <input type="text" name="categories_labels[]" value="<?= htmlspecialchars((string) $label) ?>" placeholder="libellé">
                <button type="button" class="btn btn-small btn-danger" data-remove-row>Suppr.</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="settings-group" data-group="types_cuisson">
          <div class="settings-group-head">
            <h3>Types de cuisson</h3>
            <button type="button" class="btn btn-small btn-secondary" data-add-row="types_cuisson">+ Ajouter</button>
          </div>
          <div class="settings-rows" data-rows="types_cuisson">
            <?php foreach (($currentOptions['types_cuisson'] ?? []) as $key => $label): ?>
              <div class="settings-row">
                <input type="text" name="types_cuisson_keys[]" value="<?= htmlspecialchars((string) $key) ?>" placeholder="clé">
                <input type="text" name="types_cuisson_labels[]" value="<?= htmlspecialchars((string) $label) ?>" placeholder="libellé">
                <button type="button" class="btn btn-small btn-danger" data-remove-row>Suppr.</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="settings-group" data-group="types_recette">
          <div class="settings-group-head">
            <h3>Types de recette</h3>
            <button type="button" class="btn btn-small btn-secondary" data-add-row="types_recette">+ Ajouter</button>
          </div>
          <div class="settings-rows" data-rows="types_recette">
            <?php foreach (($currentOptions['types_recette'] ?? []) as $key => $label): ?>
              <div class="settings-row">
                <input type="text" name="types_recette_keys[]" value="<?= htmlspecialchars((string) $key) ?>" placeholder="clé">
                <input type="text" name="types_recette_labels[]" value="<?= htmlspecialchars((string) $label) ?>" placeholder="libellé">
                <button type="button" class="btn btn-small btn-danger" data-remove-row>Suppr.</button>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <div class="settings-actions">
        <button type="submit" class="btn btn-primary">Enregistrer les options</button>
      </div>
    </form>
  </section>
</div>

<template id="settings-row-template">
  <div class="settings-row">
    <input type="text" placeholder="clé">
    <input type="text" placeholder="libellé">
    <button type="button" class="btn btn-small btn-danger" data-remove-row>Suppr.</button>
  </div>
</template>

<script>
(() => {
  const template = document.getElementById('settings-row-template');
  if (!template) return;

  document.querySelectorAll('[data-add-row]').forEach((button) => {
    button.addEventListener('click', () => {
      const group = button.getAttribute('data-add-row');
      const rows = document.querySelector(`[data-rows="${group}"]`);
      if (!rows) return;

      const node = template.content.firstElementChild.cloneNode(true);
      const inputs = node.querySelectorAll('input');
      if (inputs.length === 2) {
        inputs[0].name = `${group}_keys[]`;
        inputs[1].name = `${group}_labels[]`;
      }
      rows.appendChild(node);
    });
  });

  document.addEventListener('click', (event) => {
    const target = event.target;
    if (!(target instanceof HTMLElement)) return;
    if (!target.matches('[data-remove-row]')) return;

    const row = target.closest('.settings-row');
    const rows = row?.parentElement;
    if (!row || !rows) return;
    if (rows.querySelectorAll('.settings-row').length <= 1) return;
    row.remove();
  });

  const goToRowHref = (row) => {
    if (!(row instanceof HTMLElement)) return;
    const href = row.getAttribute('data-href');
    if (!href) return;
    window.location.href = href;
  };

  document.querySelectorAll('tr.settings-click-row').forEach((row) => {
    row.addEventListener('click', (event) => {
      const target = event.target;
      if (target instanceof HTMLElement && target.closest('a')) return;
      goToRowHref(row);
    });

    row.addEventListener('keydown', (event) => {
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      goToRowHref(row);
    });
  });
})();
</script>

<?php require __DIR__ . '/../ui/footer.php'; ?>
