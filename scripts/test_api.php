<?php
$config = require __DIR__ . "/../config/openai.php";

$apiKey = $config["API_KEY"] ?? null;

if (!$apiKey) {
    die("❌ Aucune clé API trouvée dans config/openai.php\n");
}

$url = "https://api.openai.com/v1/chat/completions";

$data = [
    "model" => "gpt-4.1-mini",
    "messages" => [
        ["role" => "user", "content" => "Dis-moi simplement 'API OK'"]
    ]
];

$headers = [
    "Content-Type: application/json",
    "Authorization: Bearer $apiKey"
];

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => json_encode($data),
]);

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "❌ Erreur cURL : " . curl_error($ch);
    exit;
}

echo "Réponse brute de l'API :\n\n";
echo $response;
