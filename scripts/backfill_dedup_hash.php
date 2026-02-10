<?php
declare(strict_types=1);

require __DIR__ . '/../config/database.php';

function normalize_dedup_value(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9 ]/u', ' ', $value);
    $value = preg_replace('/\s+/u', ' ', $value);
    return trim($value);
}

function compute_dedup_hash(string $titre, ?string $auteur, array $ingredients): string
{
    $normTitre = normalize_dedup_value($titre);
    $normAuteur = normalize_dedup_value($auteur ?? '');

    $firstIngredients = array_slice($ingredients, 0, 3);
    $normIngredients = array_map(
        fn($ing) => normalize_dedup_value((string) $ing),
        $firstIngredients
    );

    $payload = $normTitre . '||' . $normAuteur . '||' . implode('|', $normIngredients);
    return hash('sha256', $payload);
}

$pdo = getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Charge toutes les recettes (id, titre, auteur)
$stmt = $pdo->query("SELECT id, titre, auteur FROM recettes ORDER BY id ASC");
$recettes = $stmt->fetchAll(PDO::FETCH_ASSOC);

$getIngredients = $pdo->prepare("
    SELECT texte
    FROM ingredients
    WHERE recette_id = :id
    ORDER BY ordre ASC
");

$update = $pdo->prepare("
    UPDATE recettes
    SET dedup_hash = :hash
    WHERE id = :id
");

$hashMap = [];
$updated = 0;
$skipped = 0;
$dedupSet = [];

foreach ($recettes as $r) {
    $id = (int) $r['id'];

    $getIngredients->execute([':id' => $id]);
    $ingredients = $getIngredients->fetchAll(PDO::FETCH_COLUMN);

    $hash = compute_dedup_hash((string) $r['titre'], $r['auteur'] ?? null, $ingredients);

    // Suivi des doublons par hash
    if (!isset($hashMap[$hash])) {
        $hashMap[$hash] = [];
    }
    $hashMap[$hash][] = [
        'id' => $id,
        'titre' => $r['titre'],
        'auteur' => $r['auteur'],
    ];

    // Remplissage du champ dedup_hash
    if ($hash !== '' && !isset($dedupSet[$hash])) {
        $update->execute([
            ':hash' => $hash,
            ':id' => $id,
        ]);
        $dedupSet[$hash] = true;
        $updated++;
    } else {
        $skipped++;
    }
}

// Rapport des doublons
$duplicates = array_filter($hashMap, fn($group) => count($group) > 1);

echo "Backfill dedup_hash terminé.\n";
echo "Recettes traitées : " . count($recettes) . "\n";
echo "Recettes mises à jour : " . $updated . "\n";
echo "Recettes ignorées (hash vide) : " . $skipped . "\n\n";

if (count($duplicates) === 0) {
    echo "Aucun doublon détecté.\n";
    exit(0);
}

echo "Doublons détectés : " . count($duplicates) . " groupes\n\n";

$i = 1;
foreach ($duplicates as $hash => $group) {
    echo "Groupe #" . $i . " (hash=" . $hash . ")\n";
    foreach ($group as $row) {
        $auteur = $row['auteur'] ?? '';
        echo "  - ID " . $row['id'] . " | " . $row['titre'] . " | " . $auteur . "\n";
    }
    echo "\n";
    $i++;
}
