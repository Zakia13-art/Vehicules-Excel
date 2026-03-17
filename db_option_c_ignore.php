<?php
/**
 * OPTION C: set_trajet_ignore()
 * Uses INSERT IGNORE - inserts new trajets, silently skips duplicates
 * No errors, no rejections - just inserts what's new
 */

function set_trajet_ignore($transporteur, $veh, $parc, $dep, $vers, $debut, $fin, $penalite, $km, $chauff){
	$db = Cnx();
	
	// Clean data
	$veh = trim($veh);
	$parc = trim($parc);
	$dep = trim($dep);
	$vers = trim($vers);
	$chauff = trim($chauff);
	
	// Convert Unix timestamps to datetime
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
	
	// Clean penalty
	if (is_string($penalite) && ($penalite === '-----' || trim($penalite) === '')) {
		$penalite = 0;
	} else {
		$penalite = (int) preg_replace('/[^0-9]/', '', (string) $penalite);
	}
	
	// Clean km
	$km = (float) preg_replace('/[^0-9.]/', '', (string) $km);
	
	try {
		// ✅ OPTION C: INSERT IGNORE - silently skip duplicates
		$query = "INSERT IGNORE INTO trajets (transporteur, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur) 
		          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
		
		$stmt = $db->prepare($query);
		$result = $stmt->execute([
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
		@mkdir('logs', 0755, true);
		$file = "logs/log.txt";
		$fp = fopen($file, "a+");
		fputs($fp, date("d-m-Y H:i") . ": Erreur insert ignore trajet - " . $e->getMessage() . " - Vehicle: $veh\n");
		fclose($fp);
		return false;
	}
}

?>