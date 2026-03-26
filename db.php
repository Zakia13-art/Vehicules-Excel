<?php
function Cnx(){
        try
        {
                $pdo_options[PDO::ATTR_ERRMODE]=PDO::ERRMODE_EXCEPTION;
                $db=new PDO('mysql:host=localhost; dbname=rapport','root','');
                return $db;
        }
        catch (Exception $e)
        {
                die('Erreur:' .$e->getMessage());
                return null;
        }
}

// ========================================
// BACKUP FUNCTIONS - BACKWARDS COMPATIBLE
// ========================================

// Ancien function (gardée pour compatibilité)
function save_backup($transporteur_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur) {
    return save_backup_compat($transporteur_id, '', $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur);
}

// Nouveau function avec transporteur_nom
function save_backup_new($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur) {
    return save_backup_compat($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur);
}

// Function compatible (interne)
function save_backup_compat($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur) {
    @mkdir('backups', 0755, true);
    $backup_file = 'backups/trajets_backup_' . date('Y-m-d') . '.json';

    $data = array(
        'timestamp' => date('Y-m-d H:i:s'),
        'transporteur_id' => $transporteur_id,
        'transporteur_nom' => $transporteur_nom,
        'vehicule' => $vehicule,
        'parcour' => $parcour,
        'depart' => $depart,
        'vers' => $vers,
        'debut' => $debut,
        'fin' => $fin,
        'penalite' => $penalit,
        'km' => $km,
        'chauffeur' => $chauffeur
    );

    $json_data = json_encode($data) . "\n";
    file_put_contents($backup_file, $json_data, FILE_APPEND);
    return true;
}

// ========================================
// SAVE TO CSV - BACKWARDS COMPATIBLE
// ========================================

// Ancien function
function save_to_csv($transporteur_id, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur) {
    return save_to_csv_compat($transporteur_id, '', $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur);
}

// Nouveau function
function save_to_csv_new($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur) {
    return save_to_csv_compat($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur);
}

// Function compatible (interne)
function save_to_csv_compat($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur) {
    @mkdir('backups', 0755, true);
    $csv_file = 'backups/trajets_backup_' . date('Y-m-d') . '.csv';

    // Create header if file doesn't exist
    if (!file_exists($csv_file)) {
        $header = "Timestamp,Transporteur ID,Transporteur Nom,Vehicule,Parcour,Depart,Vers,Debut,Fin,Penalite,KM,Chauffeur\n";
        file_put_contents($csv_file, $header);
    }

    // Add data row
    $data = array(
        date('Y-m-d H:i:s'),
        $transporteur_id,
        $transporteur_nom,
        $vehicule,
        $parcour,
        $depart,
        $vers,
        $debut,
        $fin,
        $penalit,
        $km,
        $chauffeur
    );

    $csv_line = implode(',', $data) . "\n";
    file_put_contents($csv_file, $csv_line, FILE_APPEND);
    return true;
}

/**
 * ✅ set_trajet() - BACKWARDS COMPATIBLE + TRANSPORTEUR_NOM
 * Supporte ancien ET nouveau signature
 */
function set_trajet($transporteur, $param2 = null, $veh = null, $parc = null, $dep = null, $vers = null, $debut = null, $fin = null, $penalite = null, $km = null, $chauff = null){
    $db=Cnx();

    // Détection: ancien vs nouveau signature
    // Si $veh est null, alors c'est NOUVEAU signature: ($transporteur, $transporteur_nom, $veh, ...)
    // Si $veh n'est pas null, alors c'est ANCIEN signature: ($transporteur, $veh, $parc, ...)

    $transporteur_nom = '';

    if ($veh === null) {
        // NOUVEAU signature: ($transporteur, $transporteur_nom, $veh, ...)
        $transporteur_nom = $param2;
        $veh = func_get_arg(2);
        $parc = func_get_arg(3);
        $dep = func_get_arg(4);
        $vers = func_get_arg(5);
        $debut = func_get_arg(6);
        $fin = func_get_arg(7);
        $penalite = func_get_arg(8);
        $km = func_get_arg(9);
        $chauff = func_get_arg(10);
    } else {
        // ANCIEN signature: ($transporteur, $veh, $parc, ...)
        // $param2 est en fait $veh
        $veh = $param2;
        $parc = func_get_arg(2);
        $dep = func_get_arg(3);
        $vers = func_get_arg(4);
        $debut = func_get_arg(5);
        $fin = func_get_arg(6);
        $penalite = func_get_arg(7);
        $km = func_get_arg(8);
        $chauff = func_get_arg(9);

        // Pour ancien code, essayer de trouver transporteur_nom depuis ID
        $noms = [1=>'BOUTCHRAFINE', 2=>'SOMATRIN', 3=>'MARATRANS', 4=>'G.T.C', 5=>'DOUKALI',
                 6=>'COTRAMAB', 7=>'CORYAD', 8=>'CONSMETA', 9=>'CHOUROUK', 10=>'CARRE', 11=>'STB', 12=>'FASTTRANS'];
        $transporteur_nom = $noms[$transporteur] ?? '';
    }

    // Nettoyer les données
    $veh = trim($veh);
    $parc = trim($parc);
    $dep = trim($dep);
    $vers = trim($vers);
    $chauff = trim($chauff);
    $transporteur_nom = trim($transporteur_nom);

    // CONVERSION DES TIMESTAMPS CORRECTE
    if (is_numeric($debut) && $debut > 1000000000) {
        $debut_dt = date('Y-m-d H:i:s', (int)$debut);
    } else {
        $debut_dt = date('Y-m-d H:i:s');
    }

    if (is_numeric($fin) && $fin > 1000000000) {
        $fin_dt = date('Y-m-d H:i:s', (int)$fin);
    } else {
        $fin_dt = date('Y-m-d H:i:s');
    }

    // Nettoyer la pénalité
    if (is_string($penalite) && ($penalite === '-----' || trim($penalite) === '')) {
        $penalite = 0;
    } else {
        $penalite = (int) preg_replace('/[^0-9]/', '', (string) $penalite);
    }

    // Nettoyer le kilométrage
    $km = (float) preg_replace('/[^0-9.]/', '', (string) $km);

    try {
        // Vérifier les doublons
        $checkQuery = "SELECT COUNT(*) FROM trajets WHERE transporteur = ? AND vehicule = ? AND debut = ? AND fin = ?";
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$transporteur, $veh, $debut_dt, $fin_dt]);

        if ($checkStmt->fetchColumn() > 0) {
            return false; // Doublon détecté
        }

        // Insérer le trajet avec transporteur_nom (si dispo)
        if ($transporteur_nom) {
            $requete7 = "INSERT INTO trajets (transporteur, transporteur_nom, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $req = $db->prepare($requete7);
            $result = $req->execute([$transporteur, $transporteur_nom, $veh, $parc, $dep, $vers, $debut_dt, $fin_dt, $penalite, $km, $chauff]);
        } else {
            // Ancienne structure (sans transporteur_nom)
            $requete7 = "INSERT INTO trajets (transporteur, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $req = $db->prepare($requete7);
            $result = $req->execute([$transporteur, $veh, $parc, $dep, $vers, $debut_dt, $fin_dt, $penalite, $km, $chauff]);
        }

        // AUTO-BACKUP
        if ($result) {
            save_backup_compat($transporteur, $transporteur_nom, $veh, $parc, $dep, $vers, $debut, $fin, $penalite, $km, $chauff);
            save_to_csv_compat($transporteur, $transporteur_nom, $veh, $parc, $dep, $vers, $debut, $fin, $penalite, $km, $chauff);
        }

        return $result;

    } catch (Exception $e) {
        @mkdir('logs', 0755, true);
        $file = "logs/log.txt";
        $fp = fopen($file, "a+");
        fputs($fp, date("d-m-Y H:i").": Erreur insert trajet - " . $e->getMessage() . " - Véhicule: $veh\n");
        fclose($fp);
        return false;
    }
}

function listetrans(){
        $db=Cnx();
        $requete5= $db->query("SELECT *  FROM transporteurs ");
        return $requete5;
}

function listechauffeur(){
        $db=Cnx();
        $requete6= $db->query("SELECT * FROM chauffeurs");
        return $requete6;
}

function vehicule(){
        $db=Cnx();
        $requete6= $db->query("SELECT vehicule FROM trajets group by vehicule");
        return $requete6;
}

function list_trajets($cnd){
        $db=Cnx();
        $requete6= $db->query("SELECT t.*,tt.name,c.name as chauff FROM trajets t, transporteurs tt,chauffeurs c WHERE t.transporteur=tt.id and t.chauffeur=c.matricule".$cnd);
        return $requete6;
}

function note_chauffeur($cnd){
        $db=Cnx();
        $requete= $db->query("SELECT c.name,t.chauffeur,sum(t.penalite*t.kilometrage) as calcsome,sum(t.kilometrage) as totalkm,((sum(t.penalite*t.kilometrage))/(sum(t.kilometrage))) as note
FROM trajets t,chauffeurs c
where t.chauffeur=c.matricule
GROUP by t.chauffeur");
        return $requete;
}

function note_trans($cnd){
        $db=Cnx();
        $requete= $db->query("SELECT b.name,t.transporteur,sum(t.penalite*t.kilometrage) as calcsome,sum(t.kilometrage) as totalkm,((sum(t.penalite*t.kilometrage))/(sum(t.kilometrage))) as note
FROM trajets t,transporteurs b
where t.transporteur=b.id
GROUP by t.transporteur");
        return $requete;
}

function note_trans_cnd($cnd){
        $db=Cnx();
        $requete= $db->query("SELECT b.name,t.transporteur,sum(t.penalite*t.kilometrage) as calcsome,sum(t.kilometrage) as totalkm,((sum(t.penalite*t.kilometrage))/(sum(t.kilometrage))) as note
FROM trajets t,transporteurs b
where t.transporteur=b.id ".$cnd."");
        return $requete;
}

function getv_trans($id){
        $db=Cnx();
        $requete6= $db->query("SELECT vehicule FROM trajets where transporteur='".$id."' group by vehicule");
        return $requete6;
}

?>