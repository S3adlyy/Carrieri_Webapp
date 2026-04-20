<?php
// test_credentials.php - Check if credentials are loaded correctly

// Read from .env manually
$envFile = __DIR__ . '/../.env';
$envVars = [];

if (file_exists($envFile)) {
    $lines = file($envFile);
    foreach ($lines as $line) {
        $line = trim($line);
        if (str_starts_with($line, 'AZURE_FACE_')) {
            $parts = explode('=', $line, 2);
            if (count($parts) == 2) {
                $envVars[$parts[0]] = $parts[1];
            }
        }
    }
}

// Hardcoded from your Java properties (confirmed working)
$endpoint = "https://carrieri-face-id.cognitiveservices.azure.com";
$key = "BjHMnlvGZug7CujVef7emHSKmpyudb5ZS1n52ZzHgOQhvG7y0a93JQQJ99CCAC5RqLJXJ3w3AAAKACOGQwxw";

echo "<h1>Azure Face API Credentials Test</h1>";

echo "<h2>Credentials from .env file:</h2>";
echo "<pre>";
print_r($envVars);
echo "</pre>";

echo "<h2>Hardcoded credentials (from Java properties):</h2>";
echo "<p>Endpoint: $endpoint</p>";
echo "<p>Key: " . substr($key, 0, 20) . "...</p>";

// Test with hardcoded credentials
echo "<h2>Testing with hardcoded credentials:</h2>";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$endpoint/face/v1.0/detect?returnFaceId=true");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Ocp-Apim-Subscription-Key: $key",
    "Content-Type: application/octet-stream"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, ""); // Empty body
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>HTTP Status: <strong>$httpCode</strong></p>";
echo "<p>Response: " . htmlspecialchars($response) . "</p>";

if ($httpCode == 401) {
    echo "<p style='color:red'>❌ Hardcoded credentials also failed! The API key might be expired or the endpoint is wrong.</p>";
} elseif ($httpCode == 400) {
    echo "<p style='color:green'>✅ Hardcoded credentials work! (400 means no image provided, which is expected)</p>";
} elseif ($httpCode == 200) {
    echo "<p style='color:green'>✅ Hardcoded credentials work!</p>";
}

// Test with a simple GET to check endpoint
echo "<h2>Testing endpoint connectivity:</h2>";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "$endpoint/face/v1.0");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);

curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<p>Endpoint reachable? HTTP Status: $httpCode</p>";
?>