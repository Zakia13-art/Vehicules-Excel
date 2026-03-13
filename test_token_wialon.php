<?php
/**
 * test_token_wialon.php — Test du token Wialon
 * Vérifie si le token est valide et connecté
 */

// 🔴 CHANGE CE TOKEN PAR LE TIEN!
$WIALON_TOKEN = 'b6db68331b4b6ed14b61dbfeeaad9a066E746D47FF113B748F57E902F75FA585EF377D21';

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Token Wialon</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background: #f5f5f5; }
        .card { background: white; padding: 30px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        code { background: #f0f0f0; padding: 5px 10px; border-radius: 4px; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 4px; overflow-x: auto; }
        .step { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #3b82f6; }
    </style>
</head>
<body>

<h1>🔐 Test du Token Wialon</h1>

<div class="card">
    <h2>Étape 1: Vérifier la connexion API</h2>
    <?php
    
    // Test 1: Vérifier que l'API est accessible
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        echo "<p class='error'>❌ Erreur connexion: " . htmlspecialchars($error) . "</p>";
    } elseif ($http_code === 200) {
        echo "<p class='success'>✅ API Wialon accessible (HTTP 200)</p>";
    } else {
        echo "<p class='warning'>⚠️ HTTP Code: $http_code</p>";
    }
    ?>
</div>

<div class="card">
    <h2>Étape 2: Tester le Token</h2>
    
    <div class="step">
        <strong>Token actuel:</strong>
        <code><?= htmlspecialchars(substr($WIALON_TOKEN, 0, 20)) ?>...<?= htmlspecialchars(substr($WIALON_TOKEN, -10)) ?></code>
    </div>
    
    <?php
    // Test 2: Essayer de se connecter avec le token
    $curl = curl_init();
    $login_url = "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . urlencode($WIALON_TOKEN) . "\"}";
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $login_url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
    ));
    
    $response = curl_exec($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $error = curl_error($curl);
    curl_close($curl);
    
    echo "<h3>Réponse API:</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    if ($error) {
        echo "<p class='error'>❌ Erreur cURL: " . htmlspecialchars($error) . "</p>";
    } else {
        $data = json_decode($response, true);
        
        if (isset($data['eid'])) {
            echo "<p class='success'>✅ Token VALIDE!</p>";
            echo "<p>Session ID (eid): <code>" . htmlspecialchars($data['eid']) . "</code></p>";
            
            if (isset($data['user'])) {
                echo "<p>Utilisateur: <strong>" . htmlspecialchars($data['user']['nm'] ?? 'N/A') . "</strong></p>";
            }
        } elseif (isset($data['error'])) {
            echo "<p class='error'>❌ Erreur Wialon: " . htmlspecialchars($data['error']) . "</p>";
        } else {
            echo "<p class='error'>❌ Réponse inattendue de Wialon</p>";
        }
    }
    ?>
</div>

<div class="card">
    <h2>📝 Comment obtenir un nouveau token?</h2>
    
    <ol>
        <li>Va sur <strong>https://hst.wialon.com/</strong> (ou ton URL Wialon)</li>
        <li>Connecte-toi avec tes identifiants</li>
        <li>Va à <strong>Paramètres → Tokens d'accès</strong> (ou API Keys)</li>
        <li>Crée un nouveau token ou récupère un token existant</li>
        <li>Copie le token (c'est une longue chaîne de caractères)</li>
        <li>Remplace le token dans ton code par le nouveau</li>
    </ol>
</div>

<div class="card">
    <h2>🔧 Comment corriger le code?</h2>
    
    <p>Une fois que tu as ton nouveau token, modifie <strong>rapp.php</strong> et <strong>lesgets.php</strong>:</p>
    
    <pre>// À la ligne avec le token Wialon:
// AVANT:
CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"b6db68...

// APRÈS:
CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"<strong>TON_NOUVEAU_TOKEN_ICI</strong>"</pre>

    <p style="color: #666; margin-top: 20px;">
        <strong>Conseil:</strong> Store le token dans un fichier <code>config.php</code> pour plus de sécurité:
    </p>
    
    <pre>&lt;?php
define('WIALON_TOKEN', 'ton_token_ici');
?&gt;</pre>
    
    <p>Puis utilise <code>WIALON_TOKEN</code> dans les fichiers PHP.</p>
</div>

<div class="card" style="background: #fffacd; border-left-color: #ffc000;">
    <h2>⚠️ Important</h2>
    <ul>
        <li>Le token peut expirer après 30 jours</li>
        <li>Ne partage jamais le token (il donne accès à tous les données Wialon)</li>
        <li>Utilise un token avec les bonnes permissions (rapports, groupes, etc.)</li>
    </ul>
</div>

</body>
</html>