<?php
/**
 * IMPORT COMPLET - 60 DERNIERS JOURS
 */
set_time_limit(1800);
require_once __DIR__ . "/lesgets.php";

echo "======================================\n";
echo "IMPORT COMPLET - 60 DERNIERS JOURS\n";
echo "======================================\n\n";

// GROUPES AVEC IDS ORIGINAUX (tous corrects)
$tab_group = array(
	'BOUTCHRAFINE' => 12173650,
	'SOMATRIN' => 30071668,
	'MARATRANS' => 19631505,
	'G.T.C' => 30085013,
	'DOUKALI' => 19585587,
	'COTRAMAB' => 19585601,
	'CORYAD' => 19585581,
	'CONSMETA' => 19629962,
	'CHOUROUK' => 19630023,
	'CARRE' => 29440837,
	'STB' => 26577266,
	'FASTTRANS' => 19635796
);

// Modifier execRep pour 60 jours
function execRep60($group, $sid, $from1, $to1, $templateId = 1) {
    $to = time() - ($to1 * 86400);
    $from = time() - ($from1 * 86400);

    $curl = curl_init();
    $Url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=report/exec_report&params={"reportResourceId":19907460,"reportTemplateId":' . $templateId . ',"reportObjectId":' . $group . ',"reportObjectSecId":0,"interval":{"from":' . $from . ',"to":' . $to . ',"flags":0}}&sid=' . $sid;

    curl_setopt_array($curl, array(
        CURLOPT_URL => $Url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
    ));

    $response = curl_exec($curl);
    curl_close($curl);

    if (!$err) {
        $v_det = json_decode($response, true);
        if (isset($v_det['reportResult']['tables'])) {
            $nbrtab = sizeof($v_det['reportResult']['tables']);
            if ($nbrtab == 0) return null;
            $tabline = array();
            for ($i = 0; $i < $nbrtab; $i++) {
                $tabline[$i] = $v_det['reportResult']['tables'][$i]['rows'];
            }
            return $tabline;
        }
    }
    return null;
}

$sid = sid();
if (!$sid) {
    echo "Erreur session\n";
    exit;
}

echo "Session OK\n\n";

$total_trajets = 0;
$stats = array();

echo "Import en cours...\n";
echo "======================================\n\n";

foreach ($tab_group as $nom => $groupe) {
    echo "$nom... ";

    cleanRepport($sid);
    sleep(1);

    // 60 jours = from1=61, to1=1
    $report_index = execRep60($groupe, $sid, 61, 1);

    if ($report_index === null || empty($report_index)) {
        echo "⚠️ 0 trajets\n";
        $stats[$nom] = 0;
        continue;
    }

    $trajets_before = $trajectcount;

    $i = 0;
    foreach ($report_index as $value) {
        selectRes($nom, $i, $value, $sid);
        $i++;
    }

    $trajets_inserted = $trajectcount - $trajets_before;
    $stats[$nom] = $trajets_inserted;

    echo "✅ $trajets_inserted trajets\n";
    $total_trajets += $trajets_inserted;
}

echo "\n======================================\n";
echo "RÉSUMÉ:\n";
echo "======================================\n\n";

foreach ($stats as $nom => $count) {
    echo "$nom: $count trajets\n";
}

echo "\n======================================\n";
echo "TOTAL: $total_trajets trajets insérés\n";
echo "======================================\n";
?>
