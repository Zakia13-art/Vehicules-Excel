<?php
/**
 * getitemid.php - WIALON API FUNCTIONS (FINAL - Fixed Counter)
 * Consolidé: getResID, gettempID, getgroupID, execRep, selectRes
 */

require_once("db.php");

// 🔐 TOKEN VALIDE
define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0605EA995CF621CE53D5C01A0A29C9FCFB6B2902A8');

// Compteur global pour les trajets insérés
$trajectcount = 0;

// Correspondance Wialon ↔ Base de données
$groupe_to_transporteur = array(
	'BOUTCHRAFINE' => 1,
	'SOMATRIN' => 2,
	'MARATRANS' => 3,
	'G.T.C' => 4,
	'DOUKALI' => 5,
	'COTRAMAB' => 6,
	'CORYAD' => 7,
	'CONSMETA' => 8,
	'CHOUROUK' => 9,
	'CARRE' => 10,
	'STB' => 11,
	'FASTTRANS' => 12
);

// Chauffeur par défaut
global $chauffeur_defaut;
$chauffeur_defaut = 'CD000';

// ========================================
// 🔍 SEARCH FUNCTIONS
// ========================================

function getResID($name, $sid){
	$curl = curl_init();
	$Url='https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_resource","propName":"sys_name","propValueMask":"*'.$name.'*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid='.$sid;
	curl_setopt_array($curl, array(
		CURLOPT_URL => $Url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if (!$err && isset(json_decode($response, true)['items'][0]['id'])) {
		$v_det = json_decode($response, true);
		return $v_det['items'][0]['id'];
	} else {
		@mkdir('logs', 0755, true);
		file_put_contents("logs/log.txt", date("d-m-Y H:i").": erreur getResID \n", FILE_APPEND);
		return null;
	}
}

function gettempID($name, $sid){
	$curl = curl_init();
	$Url='https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_resource","propName":"reporttemplates","propValueMask":"*'.$name.'*","sortType":"reporttemplates","propType":"propitemname"},"force":1,"flags":8192,"from":0,"to":0}&sid='.$sid;
	curl_setopt_array($curl, array(
		CURLOPT_URL => $Url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if (!$err && isset(json_decode($response, true)['items'][0]['rep'][1]['id'])) {
		$v_det = json_decode($response, true);
		return $v_det['items'][0]['rep'][1]['id'];
	} else {
		@mkdir('logs', 0755, true);
		file_put_contents("logs/log.txt", date("d-m-Y H:i").": erreur gettempID \n", FILE_APPEND);
		return null;
	}
}

function getgroupID($name, $sid){
	$curl = curl_init();
	$Url='https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_unit_group","propName":"sys_name","propValueMask":"*'.$name.'*","sortType":"sys_name","propType":"property"},"force":1,"flags":1,"from":0,"to":0}&sid='.$sid;
	curl_setopt_array($curl, array(
		CURLOPT_URL => $Url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if (!$err && isset(json_decode($response, true)['items'][0]['id'])) {
		$v_det = json_decode($response, true);
		return $v_det['items'][0]['id'];
	} else {
		@mkdir('logs', 0755, true);
		file_put_contents("logs/log.txt", date("d-m-Y H:i").": erreur getgroupID \n", FILE_APPEND);
		return null;
	}
}

// ========================================
// 📋 REPORT FUNCTIONS
// ========================================

function execRep($group, $sid, $from1=0, $to1=0){
	// Utiliser time() actuel au lieu de base_time fixe
	if ($from1 > 0 || $to1 > 0) {
		$to = time();
		$from = $to - ($from1 * 86400);
	} else {
		$to = time();
		$from = $to - (7 * 86400);
	}

	$curl = curl_init();
	$Url='https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":22861605,"reportTemplateId":1,"reportObjectId":'.$group.',"reportObjectSecId":0,"interval":{"from":'.$from.',"to":'.$to.',"flags":0}}&sid='.$sid;
	
	curl_setopt_array($curl, array(
		CURLOPT_URL => $Url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));
	
	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if (!$err) {
		$v_det = json_decode($response, true);
		if (isset($v_det['reportResult']['tables'])) {
			$nbrtab = sizeof($v_det['reportResult']['tables']);
			// Si tables est vide, retourner null (pas de données)
			if ($nbrtab == 0) {
				return null;
			}
			$tabline = array();
			$i = 0;
			while($i < $nbrtab){
				$tabline[$i] = $v_det['reportResult']['tables'][$i]['rows'];
				$i++;
			}
			return $tabline;
		}
	} 
	
	@mkdir('logs', 0755, true);
	file_put_contents("logs/log.txt", date("d-m-Y H:i").": erreur execRep \n", FILE_APPEND);
	return null;
}

/**
 * selectRes - Récupère et sauvegarde les résultats de rapport
 * ✅ FIXED: Proper counter increment + TRANSPORTEUR_NOM
 */
function selectRes($groupe, $tabindex, $to, $sid){
	global $trajectcount, $groupe_to_transporteur, $chauffeur_defaut;

	$curl = curl_init();
	$Url='https://hst-api.wialon.com/wialon/ajax.html?svc=report/select_result_rows&params={"tableIndex":'.$tabindex.',"config":{"type":"range","data":{"from":0,"to":'.$to.',"level":2}}}&sid='.$sid;
	curl_setopt_array($curl, array(
		CURLOPT_URL => $Url,
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT => 60,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_HTTPHEADER => array("cache-control: no-cache", "content-type: application/x-www-form-urlencoded"),
	));

	$response = curl_exec($curl);
	$err = curl_error($curl);
	curl_close($curl);

	if (!$err) {
		$v_det = json_decode($response, true);
		if (is_array($v_det) && isset($v_det[0]['r'])) {
			$i = 0;
			while($i < count($v_det)){
				if (isset($v_det[$i]['r'])) {
					foreach($v_det[$i]['r'] as $tabline){
						// Extraire les données Wialon
						$vehicule = str_replace(" ", "", str_replace("-", "", str_replace("/", "", $tabline['c']['1'] ?? '')));
						$parcour = $tabline['c']['2'] ?? '';
						$depart = $tabline['c']['3'] ?? '';
						$vers = $tabline['c']['4'] ?? '';
						$debut = $tabline['t1'] ?? 0;
						$fin = $tabline['t2'] ?? 0;
						$penalit = $tabline['c']['8'] ?? 0;
						$km = (float)str_replace("km", "", $tabline['c']['9'] ?? 0);

						// Récupérer l'ID et le NOM du transporteur
						$transporteur_id = $groupe_to_transporteur[$groupe] ?? 1;
						$transporteur_nom = $groupe; // Le nom du groupe est passé en paramètre

						// Insérer le trajet avec transporteur_nom
						$result = set_trajet($transporteur_id, $transporteur_nom, $vehicule, $parcour, $depart, $vers, $debut, $fin, $penalit, $km, $chauffeur_defaut);

						// Increment counter when insert succeeds
						if ($result) {
							$trajectcount++;
						}
					}
				}
				$i++;
			}
		}
	} else {
		@mkdir('logs', 0755, true);
		file_put_contents("logs/log.txt", date("d-m-Y H:i").": erreur selectRes - $err \n", FILE_APPEND);
	}
}

?>