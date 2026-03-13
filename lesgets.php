<?php
set_time_limit(600);
require_once("getitemid.php");

function sid(){
 $curl = curl_init();
 curl_setopt_array($curl, array(
            CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=token/login&params={\"token\":\"" . WIALON_TOKEN . "\"}",
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

$sid="";
 if (!$err)
 {
      $v_det = json_decode($response,true);
	  $sid=$v_det['eid'] ?? '';
 }
 return $sid;
}

function cleanRepport($sid){
 $curl = curl_init();
 curl_setopt_array($curl, array(
            CURLOPT_URL => "https://hst-api.wialon.com/wialon/ajax.html?svc=report/cleanup_result&params={}&sid=".$sid,
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
	 curl_exec($curl);
	 curl_close($curl);
}

$sid=sid();

if (!$sid) {
	echo "❌ Erreur: Impossible de créer une session Wialon<br>";
	exit;
}

$tab_group=array(
	'BOUTCHRAFINE'=>19022033,
	'SOMATRIN'=>19596491,
	'MARATRANS'=>19631505,
	'G.T.C'=>19590737,
	'DOUKALI'=>19585587,
	'COTRAMAB'=>19585601,
	'CORYAD'=>19585581,
	'CONSMETA'=>19629962,
	'CHOUROUK'=>19630023,
	'CARRE'=>19643391,
	'STB'=>19585942,
	'FASTTRANS'=>19635796
);

echo "<h1>Récupération des trajets depuis Wialon</h1>";
echo "Session créée: <strong>$sid</strong><br><br>";

// ✅ Compteur global des trajets insérés
$total_tables=0;
$total_trajets=0;

foreach($tab_group as $nom => $groupe){
	echo "Traitement: <strong>$nom</strong>... ";
	
	cleanRepport($sid);
	sleep(1);
	$report_index=execRep($groupe,$sid);
	
	if ($report_index === null) {
		echo "⚠️ Pas de données<br>";
		continue;
	}
	
	// ✅ Mémoriser le nombre de trajets AVANT ce groupe
	$trajets_before = $trajectcount;
	
	$i=0;
	foreach($report_index as $value){
		selectRes($groupe,$i,$value,$sid);
		$i++;
		$total_tables++;
	}
	
	// ✅ Calculer le nombre de trajets INSÉRÉS pour ce groupe
	$trajets_inserted = $trajectcount - $trajets_before;
	echo "✅ OK ($i tables, <strong>$trajets_inserted trajets insérés</strong>)<br>";
	$total_trajets += $trajets_inserted;
}

echo "<hr>";
echo "✅ Récupération terminée!<br>";
echo "Tables traitées: <strong>$total_tables</strong><br>";
echo "📊 Trajets insérés en base de données: <strong style='color: green; font-size: 1.2em;'>$total_trajets</strong><br>";

// ✅ Log pour debugging
error_log("[lesgets.php] Récupération terminée - Tables: $total_tables, Trajets: $total_trajets");

?>