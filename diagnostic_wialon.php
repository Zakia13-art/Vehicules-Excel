<?php
/*
Script de diagnostic pour vérifier la connexion à l'API Wialon
*/

// Token à tester
$TOKEN = 'b6db68331b4b6ed14b61dbfeeaad9a066E746D47FF113B748F57E902F75FA585EF377D21';

echo "<h1>Diagnostic API Wialon</h1>";
echo "<hr>";

// ══════════════════════════════════════════════════════════
// ── Test 1: Vérifier la connectivité cURL ────────────────
// ══════════════════════════════════════════════════════════

echo "<h2>1. Test cURL</h2>";
if (extension_loaded('curl')) {
    echo "✅ Extension cURL est installée<br>";
} else {
    echo "❌ Extension cURL n'est pas installée<br>";
    die("Veuillez installer l'extension cURL");
}

// ══════════════════════════════════════════════════════════
// ── Test 2: Tester la connexion au serveur Wialon ────────
// ══════════════════════════════════════════════════════════

echo "<h2>2. Test de connexion au serveur Wialon</h2>";

$curl = curl_init();
$url = "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . $TOKEN . "\"}";

echo "<p><strong>URL d'appel :</strong></p>";
echo "<pre>" . htmlspecialchars($url) . "</pre>";

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_HTTPHEADER => array(
        "cache-control: no-cache",
        "content-type: application/x-www-form-urlencoded"
    ),
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
));

$response = curl_exec($curl);
$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "❌ Erreur cURL: <strong>" . htmlspecialchars($err) . "</strong><br>";
} else {
    echo "✅ Réponse reçue (HTTP $http_code)<br>";
}

// ══════════════════════════════════════════════════════════
// ── Test 3: Analyser la réponse ──────────────────────────
// ══════════════════════════════════════════════════════════

echo "<h2>3. Analyse de la réponse</h2>";
echo "<p><strong>Réponse brute :</strong></p>";
echo "<pre>" . htmlspecialchars($response) . "</pre>";

if (!empty($response)) {
    $decoded = json_decode($response, true);
    echo "<p><strong>Réponse décodée (JSON) :</strong></p>";
    echo "<pre>" . print_r($decoded, true) . "</pre>";
    
    // Vérifier les erreurs
    if (isset($decoded['error'])) {
        echo "<p><strong style='color: red;'>❌ Erreur API: " . htmlspecialchars($decoded['error']) . "</strong></p>";
        
        // Expliquer les codes d'erreur Wialon
        $error_codes = array(
            '1' => 'Invalid session',
            '2' => 'Invalid service',
            '3' => 'Invalid result',
            '4' => 'Invalid input',
            '5' => 'Syntax error',
            '7' => 'Access denied',
            '8' => 'Other error',
            '9' => 'Token not found',
            '10' => 'Token expired',
            '11' => 'Invalid token',
        );
        
        if (isset($error_codes[$decoded['error']])) {
            echo "<p>Signification: <strong>" . $error_codes[$decoded['error']] . "</strong></p>";
        }
    }
    
    // Vérifier la session
    if (isset($decoded['eid'])) {
        echo "<p><strong style='color: green;'>✅ Session créée avec succès!</strong></p>";
        echo "<p><strong>ID de session (eid):</strong> " . htmlspecialchars($decoded['eid']) . "</p>";
    }
}

// ══════════════════════════════════════════════════════════
// ── Test 4: Informations du token ────────────────────────
// ══════════════════════════════════════════════════════════

echo "<h2>4. Informations du token</h2>";
echo "<p><strong>Token utilisé :</strong></p>";
echo "<pre>" . htmlspecialchars($TOKEN) . "</pre>";

echo "<p><strong>Longueur du token :</strong> " . strlen($TOKEN) . " caractères</p>";

// Un token valide Wialon fait généralement 64 caractères (hexadécimal)
if (strlen($TOKEN) == 64 && ctype_xdigit($TOKEN)) {
    echo "✅ Format du token semble correct (64 caractères hexadécimaux)<br>";
} else {
    echo "⚠️ Format du token peut ne pas être correct<br>";
}

// ══════════════════════════════════════════════════════════
// ── Recommandations ──────────────────────────────────────
// ══════════════════════════════════════════════════════════

echo "<h2>5. Recommandations</h2>";
echo "<ul>";
echo "<li><strong>Le token peut être expiré :</strong> Les tokens Wialon expirent après 30 jours. Vous devez en générer un nouveau depuis votre compte Wialon.</li>";
echo "<li><strong>Vérifier votre compte Wialon :</strong> Allez sur https://hosting.wialon.com et générez un nouveau token.</li>";
echo "<li><strong>Format du token :</strong> Copiez-collez exactement le token sans espaces.</li>";
echo "<li><strong>Accès API :</strong> Assurez-vous que votre compte Wialon a accès à l'API.</li>";
echo "</ul>";

echo "<hr>";
echo "<p><a href='javascript:history.back()'>&larr; Retour</a></p>";
?>