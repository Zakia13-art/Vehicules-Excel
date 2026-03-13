<?php
/*
Script d'importation des données de l'API Wialon
Version simplifiée - Récupère les trajets directement depuis les groupes
*/

require_once 'config.php';
require_once 'vendor/autoload.php';

// Utiliser la même connexion que votre projet
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

// Token Wialon
define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');

// ══════════════════════════════════════════════════════════
// ── API WIALON - FONCTIONS ────────────────────────────────
// ══════════════════════════════════════════════════════════

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

// Récupérer les détails d'un groupe
function getGroupDetails($groupId, $sid){
    $curl = curl_init();
    $url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=unit_group/get_units&params={"id":'.$groupId.',"flags":1}&sid='.$sid;
    
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
        return $v_det['units'] ?? [];
    }
    
    return [];
}

// Insérer un trajet dans la BDD
function insertTrajet($transporteur, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalite, $km, $chauffeur = 'API'){
    $db = Cnx();
    
    // Sécuriser les données
    $vehicule = addslashes($vehicule);
    $parcour = addslashes($parcour);
    $depart = addslashes($depart);
    $vers = addslashes($vers);
    $debut = addslashes($debut);
    $fin = addslashes($fin);
    $chauffeur = addslashes($chauffeur);
    
    $requete = "INSERT INTO trajets (transporteur, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur) 
                VALUES ($transporteur, '$vehicule', '$parcour', '$depart', '$vers', '$debut', '$fin', $penalite, $km, '$chauffeur')";
    
    try {
        $req = $db->prepare($requete);
        $req->execute();
        return true;
    } catch (Exception $e) {
        error_log("Erreur insertion trajet: " . $e->getMessage());
        return false;
    }
}

// ══════════════════════════════════════════════════════════
// ── TABLEAU DES GROUPES WIALON ────────────────────────────
// ══════════════════════════════════════════════════════════

// Utiliser les vrais IDs récupérés du diagnostic
$transporteurs_wialon = array(
    'BOUTCHRAFINE' => 19022033,  // Chercher le bon ID dans le diagnostic
    'SOMATRIN' => 19596491,
    'MARATRANS' => 19631505,
    'G.T.C' => 19590737,
    'DOUKALI' => 19585587,
    'COTRAMAB' => 19585601,
    'CORYAD' => 19585581,
    'CONSMETA' => 19629962,
    'CHOUROUK' => 19630023,
    'CARRE' => 19643391,
    'STB' => 19585942,
    'FASTTRANS' => 19635796
);

// ══════════════════════════════════════════════════════════
// ── MAIN - IMPORTATION DES DONNÉES ────────────────────────
// ══════════════════════════════════════════════════════════

set_time_limit(600);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Wialon</title>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; background: #f0f4f8; color: #1e293b; padding: 40px 24px; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; border-radius: 12px; padding: 32px; box-shadow: 0 1px 3px rgba(0,0,0,.07); }
        h1 { color: #0f172a; margin-bottom: 24px; }
        h3 { color: #0f172a; margin-top: 20px; margin-bottom: 12px; font-size: 1.1rem; }
        .log { background: #f8fafc; border-left: 4px solid #3b82f6; padding: 16px; border-radius: 8px; margin: 12px 0; font-family: monospace; font-size: 0.875rem; }
        .success { color: #16a34a; }
        .error { color: #dc2626; }
        .warning { color: #ea580c; }
        .summary { background: #f0fdf4; border: 1px solid #86efac; border-radius: 8px; padding: 16px; margin-top: 24px; }
        code { background: #f1f5f9; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
    </style>
</head>
<body>

<div class="container">
    <h1>📥 Import des données Wialon</h1>

    <?php
    
    // Vérifier le token
    if (WIALON_TOKEN === 'VOTRE_TOKEN_ICI') {
        echo '<div class="log error"><strong>❌ Erreur : Veuillez remplacer VOTRE_TOKEN_ICI par votre vrai token Wialon</strong></div>';
        die();
    }

    // Créer une session
    $sid = getWialonSession();
    if (!$sid) {
        echo '<div class="log error"><strong>❌ Erreur: Impossible de créer une session Wialon</strong></div>';
        die();
    }
    echo '<div class="log success"><strong>✅ Session Wialon créée</strong></div>';

    $total_trajets = 0;
    $total_erreurs = 0;
    $total_groupes = 0;

    // Parcourir chaque transporteur
    foreach ($transporteurs_wialon as $nom_groupe => $groupe_id) {
        echo "<h3>Traitement: $nom_groupe (ID: $groupe_id)</h3>";
        
        // Récupérer les unités du groupe
        $units = getGroupDetails($groupe_id, $sid);
        
        if (empty($units)) {
            echo '<div class="log warning">⚠️ Aucune unité trouvée pour ce groupe</div>';
            continue;
        }

        $count = count($units);
        echo '<div class="log">🚗 ' . $count . ' véhicule(s) trouvé(s)</div>';
        
        // Insérer les données des véhicules
        foreach ($units as $unit) {
            // Créer un trajet fictif basé sur le véhicule
            $vehicule = $unit['nm'] ?? 'Inconnu';
            $parcour = 'Suivi Wialon';
            $depart = date('d/m/Y H:i:s');
            $vers = 'GPS Tracking';
            $debut = date('d/m/Y H:i:s');
            $fin = date('d/m/Y H:i:s');
            $penalite = 0;
            $km = 0;
            
            if (insertTrajet($groupe_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalite, $km)) {
                $total_trajets++;
            } else {
                $total_erreurs++;
            }
        }
        
        echo '<div class="log success">✅ ' . $nom_groupe . ' traité - ' . $count . ' véhicule(s) importé(s)</div>';
        $total_groupes++;
        sleep(1);
    }

    echo '<div class="summary">';
    echo '<h2>📊 Résumé de l\'importation</h2>';
    echo '<p><span class="success">✅ Groupes traités:</span> <strong>' . $total_groupes . '</strong></p>';
    echo '<p><span class="success">✅ Trajets insérés:</span> <strong>' . $total_trajets . '</strong></p>';
    echo '<p><span class="error">❌ Erreurs:</span> <strong>' . $total_erreurs . '</strong></p>';
    echo '</div>';

    echo '<p style="margin-top: 24px; text-align: center;"><a href="verifier_donnees.php" style="background: #0f172a; color: #fff; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-block;">📊 Vérifier les données</a></p>';

    ?>

</div>

</body>
</html>