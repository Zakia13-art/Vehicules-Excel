<?php
/**
 * validate_import.php - Validation et dépannage
 * Exécuter pour vérifier que tout est prêt pour l'import automatique
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Validation Import Wialon</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; margin: 20px; }
        .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; }
        .check { padding: 10px; margin: 10px 0; border-left: 4px solid; border-radius: 3px; }
        .ok { border-left-color: #27ae60; background: #d5f4e6; color: #27ae60; }
        .error { border-left-color: #e74c3c; background: #fadbd8; color: #e74c3c; }
        .warning { border-left-color: #f39c12; background: #fef5e7; color: #f39c12; }
        .info { border-left-color: #3498db; background: #d6eaf8; color: #3498db; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #bdc3c7; padding: 10px; text-align: left; }
        th { background: #3498db; color: white; }
        .summary { background: #ecf0f1; padding: 15px; margin: 20px 0; border-radius: 5px; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔍 Validation - Système d'Import Wialon</h1>
    <p>Vérification de la configuration et de la préparation pour l'import automatique</p>
";

// ==========================================================
// 1. VÉRIFIER LES FICHIERS
// ==========================================================

echo '<h2>📁 Fichiers Requis</h2>';

$required_files = array(
    'getitemid.php' => 'Fonctions API Wialon',
    'db.php' => 'Connexion MySQL',
    'lesgets.php' => 'Script principal d\'import (NOUVEAU)',
    'import_trajets.bat' => 'Script de lancement Windows'
);

$files_ok = true;

foreach($required_files as $file => $description) {
    $exists = file_exists($file);
    $class = $exists ? 'ok' : 'error';
    $status = $exists ? '✅ Présent' : '❌ MANQUANT';
    
    if (!$exists) $files_ok = false;
    
    echo "<div class='check $class'>
        <strong>$file</strong> - $description<br/>
        <small>Chemin: " . dirname(__FILE__) . "/$file</small><br/>
        $status
    </div>";
}

// ==========================================================
// 2. VÉRIFIER LES RÉPERTOIRES
// ==========================================================

echo '<h2>📂 Répertoires</h2>';

$required_dirs = array(
    'logs' => 'Historique des imports',
    'backups' => 'Sauvegarde des trajets'
);

$dirs_ok = true;

foreach($required_dirs as $dir => $description) {
    $exists = is_dir($dir);
    $writable = is_writable($dir);
    $class = ($exists && $writable) ? 'ok' : 'error';
    
    if (!($exists && $writable)) $dirs_ok = false;
    
    echo "<div class='check $class'>
        <strong>$dir/</strong> - $description<br/>
        Existe: " . ($exists ? '✅ Oui' : '❌ Non') . "<br/>
        Inscriptible: " . ($writable ? '✅ Oui' : '❌ Non') . "
    </div>";
}

// ==========================================================
// 3. VÉRIFIER LA CONFIGURATION PHP
// ==========================================================

echo '<h2>⚙️ Configuration PHP</h2>';

$php_checks = array(
    'curl' => 'Extension cURL (pour API Wialon)',
    'pdo' => 'Extension PDO (pour MySQL)',
    'json' => 'Extension JSON (pour parsing)',
    'file_get_contents' => 'Fonction file_get_contents',
    'file_put_contents' => 'Fonction file_put_contents'
);

$php_ok = true;

foreach($php_checks as $check => $description) {
    if (strpos($check, 'file_') === 0) {
        $enabled = function_exists($check);
    } else {
        $enabled = extension_loaded($check);
    }
    
    $class = $enabled ? 'ok' : 'error';
    $status = $enabled ? '✅ Activée' : '❌ DÉSACTIVÉE';
    
    if (!$enabled) $php_ok = false;
    
    echo "<div class='check $class'>
        <strong>$check</strong> - $description<br/>
        $status
    </div>";
}

// ==========================================================
// 4. VÉRIFIER LA CONNEXION MySQL
// ==========================================================

echo '<h2>🔗 Connexion MySQL</h2>';

$mysql_ok = false;

try {
    // Essayer de charger db.php
    if (file_exists('db.php')) {
        require_once('db.php');
        $db = Cnx();
        
        // Vérifier la table trajets
        $result = $db->query("SELECT COUNT(*) as cnt FROM trajets");
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $trajets_count = $row['cnt'];
        
        echo "<div class='check ok'>
            <strong>✅ Connexion MySQL réussie</strong><br/>
            Trajectoires actuelles: <strong>$trajets_count</strong>
        </div>";
        
        $mysql_ok = true;
    } else {
        echo "<div class='check error'>
            <strong>❌ Fichier db.php introuvable</strong><br/>
            Impossible de vérifier la connexion MySQL
        </div>";
    }
} catch (Exception $e) {
    echo "<div class='check error'>
        <strong>❌ Erreur de connexion MySQL</strong><br/>
        Message: " . $e->getMessage() . "
    </div>";
}

// ==========================================================
// 5. VÉRIFIER LE TOKEN WIALON
// ==========================================================

echo '<h2>🔐 Token Wialon</h2>';

$wialon_ok = false;

try {
    if (file_exists('getitemid.php')) {
        require_once('getitemid.php');
        
        if (defined('WIALON_TOKEN') && WIALON_TOKEN) {
            $token = WIALON_TOKEN;
            $token_masked = substr($token, 0, 10) . '...' . substr($token, -10);
            
            echo "<div class='check ok'>
                <strong>✅ Token Wialon défini</strong><br/>
                Token: <code>$token_masked</code><br/>
                Longueur: " . strlen($token) . " caractères
            </div>";
            
            $wialon_ok = true;
        } else {
            echo "<div class='check error'>
                <strong>❌ Token Wialon non défini</strong><br/>
                Vérifier le define WIALON_TOKEN dans getitemid.php
            </div>";
        }
    }
} catch (Exception $e) {
    echo "<div class='check error'>
        <strong>❌ Erreur en vérifiant le token</strong><br/>
        Message: " . $e->getMessage() . "
    </div>";
}

// ==========================================================
// 6. VÉRIFIER LE FICHIER DE CONFIGURATION
// ==========================================================

echo '<h2>🎯 Configuration Import</h2>';

if (file_exists('config_import.json')) {
    $config = json_decode(file_get_contents('config_import.json'), true);
    $last_import = date('Y-m-d H:i:s', $config['last_import']);
    
    echo "<div class='check ok'>
        <strong>✅ Fichier config_import.json trouvé</strong><br/>
        Dernière importation: <strong>$last_import</strong><br/>
        Total cumulé: " . ($config['total_imported'] ?? 'N/A') . " trajets
    </div>";
} else {
    echo "<div class='check warning'>
        <strong>⚠️ Fichier config_import.json non trouvé</strong><br/>
        Il sera créé lors du premier lancement
    </div>";
}

// ==========================================================
// 7. VÉRIFIER LES SCRIPTS
// ==========================================================

echo '<h2>📝 Vérification des Scripts</h2>';

// Vérifier lesgets.php
if (file_exists('lesgets.php')) {
    $content = file_get_contents('lesgets.php');
    $has_load_config = strpos($content, 'load_import_config') !== false;
    $has_dynamic_time = strpos($content, 'time()') !== false;
    
    $class = ($has_load_config && $has_dynamic_time) ? 'ok' : 'warning';
    
    echo "<div class='check $class'>
        <strong>lesgets.php</strong><br/>
        Fonction load_import_config: " . ($has_load_config ? '✅' : '❌') . "<br/>
        Timestamps dynamiques (time()): " . ($has_dynamic_time ? '✅' : '❌') . "
    </div>";
}

// Vérifier getitemid.php
if (file_exists('getitemid.php')) {
    $content = file_get_contents('getitemid.php');
    $has_base_time_old = strpos($content, '$base_time = 1575503999') !== false;
    $has_dynamic_time = preg_match('/time\(\).*86400/', $content);
    
    $class = !$has_base_time_old && $has_dynamic_time ? 'ok' : 'warning';
    
    echo "<div class='check $class'>
        <strong>getitemid.php</strong><br/>
        ❌ Ne pas avoir $base_time = 1575503999 (2019)<br/>
        ✅ Doit avoir: \$to = time(); \$from = \$to - (\$from1 * 86400);
    </div>";
    
    if ($has_base_time_old) {
        echo "<div class='check error'>
            <strong>⚠️ PROBLÈME DÉTECTÉ</strong><br/>
            getitemid.php contient \$base_time = 1575503999 (Décembre 2019)<br/>
            Cela doit être remplacé par: \$to = time(); \$from = \$to - (\$from1 * 86400);
        </div>";
    }
}

// ==========================================================
// 8. TEST DE CONNEXION WIALON
// ==========================================================

echo '<h2>🔌 Test de Connexion API Wialon</h2>';

if (defined('WIALON_TOKEN') && WIALON_TOKEN && extension_loaded('curl')) {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    $http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    
    if (!$err && $http_code === 200) {
        $data = json_decode($response, true);
        
        if (isset($data['eid'])) {
            echo "<div class='check ok'>
                <strong>✅ Connexion Wialon réussie!</strong><br/>
                Session créée: " . substr($data['eid'], 0, 20) . "...
            </div>";
        } else {
            echo "<div class='check error'>
                <strong>❌ Token invalide ou expiré</strong><br/>
                Réponse API: " . json_encode($data) . "
            </div>";
        }
    } else {
        echo "<div class='check error'>
            <strong>❌ Impossible de se connecter à Wialon API</strong><br/>
            Code HTTP: $http_code<br/>
            Erreur: " . ($err ? $err : 'Réponse vide') . "
        </div>";
    }
} else {
    echo "<div class='check warning'>
        <strong>⚠️ Test de connexion Wialon non possible</strong><br/>
        (Token non défini ou cURL non activée)
    </div>";
}

// ==========================================================
// RÉSUMÉ FINAL
// ==========================================================

$all_ok = $files_ok && $dirs_ok && $php_ok && $mysql_ok && $wialon_ok;

echo '<h2>📊 Résumé</h2>';

echo '<table>';
echo '<tr><th>Composant</th><th>Statut</th></tr>';
echo '<tr><td>Fichiers</td><td style="color: ' . ($files_ok ? '#27ae60' : '#e74c3c') . ';">' . ($files_ok ? '✅ OK' : '❌ PROBLÈME') . '</td></tr>';
echo '<tr><td>Répertoires</td><td style="color: ' . ($dirs_ok ? '#27ae60' : '#e74c3c') . ';">' . ($dirs_ok ? '✅ OK' : '❌ PROBLÈME') . '</td></tr>';
echo '<tr><td>PHP</td><td style="color: ' . ($php_ok ? '#27ae60' : '#e74c3c') . ';">' . ($php_ok ? '✅ OK' : '❌ PROBLÈME') . '</td></tr>';
echo '<tr><td>MySQL</td><td style="color: ' . ($mysql_ok ? '#27ae60' : '#e74c3c') . ';">' . ($mysql_ok ? '✅ OK' : '❌ PROBLÈME') . '</td></tr>';
echo '<tr><td>Wialon API</td><td style="color: ' . ($wialon_ok ? '#27ae60' : '#e74c3c') . ';">' . ($wialon_ok ? '✅ OK' : '❌ PROBLÈME') . '</td></tr>';
echo '</table>';

if ($all_ok) {
    echo "<div class='summary' style='background: #d5f4e6; border: 2px solid #27ae60;'>
        <h3 style='color: #27ae60;'>✅ Système PRÊT pour l'import automatique!</h3>
        <p>Tous les éléments sont en place. Vous pouvez lancer <code>import_trajets_IMPROVED.bat</code></p>
    </div>";
} else {
    echo "<div class='summary' style='background: #fadbd8; border: 2px solid #e74c3c;'>
        <h3 style='color: #e74c3c;'>❌ Problèmes détectés</h3>
        <p>Veuillez corriger les problèmes marqués en rouge avant de lancer l'import</p>
    </div>";
}

echo '</div></body></html>';

?>