<?php
set_time_limit(1200);
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

$tab_group=array('BOUTCHRAFINE'=>19022033);

echo "<h1>Import des trajets depuis Wialon</h1>";
echo "Session créée: <strong>$sid</strong><br><br>";

$i=161;
$from1=0;
$to1=0;

// ✅ Variables de comptage corrigées
$total_plages=0;
$total_tables=0;
$total_trajets=0;

while($i>1){
	$from1=$i+1;
	$to1=$i-4;
	$i=$i-4;
	
	echo "<strong>Plage: du jour $from1 au $to1</strong> - ";
	
	// ✅ Compteur pour cette plage
	$trajets_plage_before = $trajectcount;
	$tables_plage = 0;
	
	foreach($tab_group as $nom => $groupe){
		cleanRepport($sid);
		sleep(1);
		$report_index=execRep($groupe,$sid,$from1,$to1);
		
		if ($report_index === null) {
			echo "⚠️ Pas de données pour $nom<br>";
			continue;
		}
		
		$j=0;
		foreach($report_index as $value){
			selectRes($groupe,$j,$value,$sid);
			$j++;
			$tables_plage++;
			$total_tables++;
		}
		
		// ✅ Trajets insérés dans cette plage
		$trajets_plage = $trajectcount - $trajets_plage_before;
		echo "✅ $nom traité ($tables_plage tables, <strong>$trajets_plage trajets</strong>)<br>";
		$total_trajets += $trajets_plage;
		$total_plages++;
	}
	echo "<br>";
}

echo "<hr>";
echo "✅ Importation terminée!<br>";
echo "Plages traitées: <strong>$total_plages</strong><br>";
echo "Tables traitées: <strong>$total_tables</strong><br>";
echo "📊 Trajets insérés en base de données: <strong style='color: green; font-size: 1.2em;'>$total_trajets</strong><br>";

// ✅ Log pour debugging
error_log("[rapp.php] Importation terminée - Plages: $total_plages, Tables: $total_tables, Trajets: $total_trajets");

?>