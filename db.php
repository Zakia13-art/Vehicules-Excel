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



function set_trajet($transporteur,$veh,$parc,$dep,$vers,$debut,$fin,$penalite,$km,$chauff){
	$db=Cnx();
	
	// Nettoyer les données
	$veh = trim($veh);
	$parc = trim($parc);
	$dep = trim($dep);
	$vers = trim($vers);
	$chauff = trim($chauff);
	
	// Convertir les timestamps Unix en format datetime
	if (is_numeric($debut) && $debut > 0) {
		$debut = date('Y-m-d H:i:s', $debut);
	} else {
		$debut = date('Y-m-d H:i:s');
	}
	
	if (is_numeric($fin) && $fin > 0) {
		$fin = date('Y-m-d H:i:s', $fin);
	} else {
		$fin = date('Y-m-d H:i:s');
	}
	
	// Nettoyer la pénalité (si c'est '-----' ou vide, mettre 0)
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
		$checkStmt->execute([$transporteur, $veh, $debut, $fin]);
		
		if ($checkStmt->fetchColumn() > 0) {
			return false; // Doublon détecté
		}
		
		// Insérer le trajet avec prepared statement sécurisé
		$requete7 = "INSERT INTO trajets (transporteur, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur) 
		             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		
		$req = $db->prepare($requete7);
		$result = $req->execute([
			$transporteur,
			$veh,
			$parc,
			$dep,
			$vers,
			$debut,
			$fin,
			$penalite,
			$km,
			$chauff
		]);
		
		return $result;
		
	} catch (Exception $e) {
		// Log l'erreur
		@mkdir('logs', 0755, true);
		$file = "logs/log.txt";
		$fp = fopen($file, "a+");
		fputs($fp, date("d-m-Y H:i").": Erreur insert trajet - " . $e->getMessage() . " - Véhicule: $veh, Penalite: $penalite, KM: $km\n");
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
	/*echo "SELECT b.name,t.transporteur,sum(t.penalite*t.kilometrage) as calcsome,sum(t.kilometrage) as totalkm,((sum(t.penalite*t.kilometrage))/(sum(t.kilometrage))) as note 
FROM trajets t,transporteurs b
where t.transporteur=b.id ".$cnd."";*/
	$requete= $db->query("SELECT b.name,t.transporteur,sum(t.penalite*t.kilometrage) as calcsome,sum(t.kilometrage) as totalkm,((sum(t.penalite*t.kilometrage))/(sum(t.kilometrage))) as note 
FROM trajets t,transporteurs b
where t.transporteur=b.id ".$cnd."");
	
	return $requete;
	
}
function getv_trans($id){
	$db=Cnx();
	//echo "SELECT vehicule FROM trajets group by vehicule where transporteur='".$id."'";
	$requete6= $db->query("SELECT vehicule FROM trajets where transporteur='".$id."' group by vehicule");
	
	return $requete6;


}
?>