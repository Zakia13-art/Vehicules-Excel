<?php
/**
 * OPTION B: set_trajet_update()
 * Inserts new trajets OR UPDATES existing ones
 * No duplicates rejected - everything gets saved or updated
 */

function set_trajet_update($transporteur, $veh, $parc, $dep, $vers, $debut, $fin, $penalite, $km, $chauff){
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
		// ✅ OPTION B: Check if exists, then UPDATE or INSERT
		$checkQuery = "SELECT id FROM trajets WHERE transporteur = ? AND vehicule = ? AND debut = ? AND fin = ?";
		$checkStmt = $db->prepare($checkQuery);
		$checkStmt->execute([$transporteur, $veh, $debut, $fin]);
		
		$existing = $checkStmt->fetch();
		
		if ($existing) {
			// UPDATE existing trajet
			$updateQuery = "UPDATE trajets SET parcour = ?, depart = ?, vers = ?, penalite = ?, kilometrage = ?, chauffeur = ? 
			                WHERE transporteur = ? AND vehicule = ? AND debut = ? AND fin = ?";
			
			$updateStmt = $db->prepare($updateQuery);
			$result = $updateStmt->execute([
				$parc,
				$dep,
				$vers,
				$penalite,
				$km,
				$chauff,
				$transporteur,
				$veh,
				$debut,
				$fin
			]);
			
			return $result;
		} else {
			// INSERT new trajet
			$insertQuery = "INSERT INTO trajets (transporteur, vehicule, parcour, depart, vers, debut, fin, penalite, kilometrage, chauffeur) 
			                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
			
			$insertStmt = $db->prepare($insertQuery);
			$result = $insertStmt->execute([
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
		}
		
	} catch (Exception $e) {
		@mkdir('logs', 0755, true);
		$file = "logs/log.txt";
		$fp = fopen($file, "a+");
		fputs($fp, date("d-m-Y H:i") . ": Erreur insert/update trajet - " . $e->getMessage() . " - Vehicle: $veh\n");
		fclose($fp);
		return false;
	}
}

?>