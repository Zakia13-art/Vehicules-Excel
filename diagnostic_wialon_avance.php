<?php
/*
Script de diagnostic pour l'API Wialon
Vérifie les ressources, templates et groupes disponibles
*/

define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB'); // À remplacer par votre token

// Connexion BDD
function Cnx(){
    try 
    {
        $pdo_options[PDO::ATTR_ERRMODE]=PDO::ERRMODE_EXCEPTION;
        $db=new PDO('mysql:host=localhost; dbname=repport','root','');
        return $db;
    }
    catch (Exception $e) 
    {
        die('Erreur:' .$e->getMessage());
        return null;
    }
}

// Créer une session Wialon
function getWialonSession(){
    $curl = curl_init();
    $url = "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}";
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ),
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    if (!$err) {
        $v_det = json_decode($response, true);
        return $v_det['eid'] ?? null;
    }
    
    return null;
}

// Récupérer les ressources
function getResources($sid){
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_resource","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":100}&sid='.$sid;
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ),
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    if (!empty($response)) {
        return json_decode($response, true);
    }
    
    return null;
}

// Récupérer les groupes
function getGroups($sid){
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":100}&sid='.$sid;
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/x-www-form-urlencoded"
        ),
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    if (!empty($response)) {
        return json_decode($response, true);
    }
    
    return null;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Wialon - Ressources et Groupes</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; min-height: 100vh; padding: 40px 24px; }
        .container { max-width: 1200px; margin: 0 auto; }
        h1 { color: #0f172a; margin-bottom: 24px; }
        h2 { color: #0f172a; margin: 24px 0 16px; font-size: 1.25rem; }
        .card { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        table { width: 100%; border-collapse: collapse; }
        th { background: #f8fafc; padding: 12px 14px; text-align: left; font-size: 0.75rem; font-weight: 600; color: #64748b; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; font-size: 0.875rem; }
        pre { background: #f8fafc; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 0.8rem; margin: 12px 0; }
        .success { color: #16a34a; }
        .error { color: #dc2626; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<div class="container">
    <h1>🔍 Diagnostic Wialon - Ressources et Groupes</h1>

    <?php
    // Vérifier que le token est rempli
    if (WIALON_TOKEN === 'VOTRE_TOKEN_ICI') {
        echo '<div class="card" style="border-left: 4px solid #dc2626;">';
        echo '<p class="error"><strong>❌ Erreur : Veuillez remplacer VOTRE_TOKEN_ICI par votre vrai token Wialon</strong></p>';
        echo '</div>';
        die();
    }

    echo '<div class="card">';
    echo '<p>Vérification des ressources et groupes disponibles dans votre compte Wialon...</p>';
    echo '</div>';

    // Créer une session
    $sid = getWialonSession();
    if (!$sid) {
        echo '<div class="card" style="border-left: 4px solid #dc2626;">';
        echo '<p class="error"><strong>❌ Impossible de créer une session Wialon. Vérifiez votre token.</strong></p>';
        echo '</div>';
        die();
    }

    echo '<div class="card">';
    echo '<p class="success"><strong>✅ Session créée avec succès</strong></p>';
    echo '</div>';

    // ══════════════════════════════════════════════════════════
    // ── Récupérer les ressources ──────────────────────────────
    // ══════════════════════════════════════════════════════════

    echo '<div class="card">';
    echo '<h2>📦 Ressources disponibles</h2>';
    
    $resources = getResources($sid);
    
    if ($resources && isset($resources['items']) && !empty($resources['items'])) {
        echo '<table>';
        echo '<thead><tr><th>Nom</th><th>ID</th><th>Type</th></tr></thead>';
        echo '<tbody>';
        foreach ($resources['items'] as $resource) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($resource['nm']) . '</strong></td>';
            echo '<td><code>' . htmlspecialchars($resource['id']) . '</code></td>';
            echo '<td>' . htmlspecialchars($resource['cls'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p style="margin-top: 12px; color: #64748b; font-size: 0.875rem;">Total: <strong>' . count($resources['items']) . '</strong> ressources</p>';
    } else {
        echo '<p class="error"><strong>❌ Aucune ressource trouvée</strong></p>';
    }
    echo '</div>';

    // ══════════════════════════════════════════════════════════
    // ── Récupérer les groupes ────────────────────────────────
    // ══════════════════════════════════════════════════════════

    echo '<div class="card">';
    echo '<h2>🚗 Groupes de véhicules disponibles</h2>';
    
    $groups = getGroups($sid);
    
    if ($groups && isset($groups['items']) && !empty($groups['items'])) {
        echo '<table>';
        echo '<thead><tr><th>Nom</th><th>ID</th><th>Type</th></tr></thead>';
        echo '<tbody>';
        foreach ($groups['items'] as $group) {
            echo '<tr>';
            echo '<td><strong>' . htmlspecialchars($group['nm']) . '</strong></td>';
            echo '<td><code>' . htmlspecialchars($group['id']) . '</code></td>';
            echo '<td>' . htmlspecialchars($group['cls'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
        echo '<p style="margin-top: 12px; color: #64748b; font-size: 0.875rem;">Total: <strong>' . count($groups['items']) . '</strong> groupes</p>';
    } else {
        echo '<p class="error"><strong>❌ Aucun groupe trouvé</strong></p>';
    }
    echo '</div>';

    // ══════════════════════════════════════════════════════════
    // ── Afficher les IDs pour import_wialon.php ───────────────
    // ══════════════════════════════════════════════════════════

    echo '<div class="card" style="background: #f0fdf4; border-left: 4px solid #16a34a;">';
    echo '<h2>✅ Utiliser ces IDs dans import_wialon.php</h2>';
    echo '<p>Remplacez le tableau <code>$transporteurs_wialon</code> par :</p>';
    
    if ($groups && isset($groups['items']) && !empty($groups['items'])) {
        echo '<pre>$transporteurs_wialon = array(';
        foreach ($groups['items'] as $group) {
            echo "\n    '" . htmlspecialchars($group['nm']) . "' => " . htmlspecialchars($group['id']) . ",";
        }
        echo "\n);</pre>";
    } else {
        echo '<p class="error">Aucun groupe disponible pour générer le code</p>';
    }
    echo '</div>';

    // ══════════════════════════════════════════════════════════
    // ── Informations sur les ressources de rapport ────────────
    // ══════════════════════════════════════════════════════════

    echo '<div class="card" style="background: #fef3f2; border-left: 4px solid #f97316;">';
    echo '<h2>⚠️ Ressource de rapport</h2>';
    echo '<p>Le code utilise actuellement la ressource avec ID <code>19907460</code>.</p>';
    echo '<p>Vérifiez que cette ressource existe et contient le template de rapport <code>1</code>.</p>';
    
    if ($resources && isset($resources['items'])) {
        $found = false;
        foreach ($resources['items'] as $resource) {
            if ($resource['id'] == 19907460) {
                echo '<p class="success"><strong>✅ Ressource 19907460 trouvée : ' . htmlspecialchars($resource['nm']) . '</strong></p>';
                $found = true;
                break;
            }
        }
        if (!$found) {
            echo '<p class="error"><strong>❌ Ressource 19907460 NON TROUVÉE</strong></p>';
            echo '<p>Changez le resourceId dans import_wialon.php par l\'un des IDs ci-dessus.</p>';
        }
    }
    echo '</div>';

    ?>

</div>

</body>
</html>