<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "lesgets.php";

echo "<h1>🔍 CHERCHER RESOURCE AVEC TEMPLATES</h1>";
echo "<pre>";

$sid = sid();
if (!$sid) {
    echo "❌ Erreur connexion\n";
    exit;
}

echo "✅ Connecté à Wialon\n\n";

// Chercher resources avec templates
$curl = curl_init();
$url = 'https://hst-api.wialon.com/wialon/ajax.html?svc=core/search_items&params={"spec":{"itemsType":"avl_resource","propName":"reporttemplates","propValueMask":"*","sortType":"reporttemplates","propType":"propitemname"},"force":1,"flags":8192,"from":0,"to":0}&sid=' . $sid;

curl_setopt_array($curl, array(
    CURLOPT_URL => $url,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => false,
));

$response = curl_exec($curl);
curl_close($curl);

$data = json_decode($response, true);

echo "=== RECHERCHE DE RESSOURCES AVEC TEMPLATES ===\n\n";

if (isset($data['items'])) {
    echo "Trouvé " . count($data['items']) . " resources\n\n";

    foreach ($data['items'] as $idx => $res) {
        $res_id = $res['id'] ?? '???';
        $res_name = $res['nm'] ?? 'Sans nom';

        if (isset($res['rep']) && !empty($res['rep'])) {
            echo "[$idx] Resource ID: $res_id | Nom: $res_name\n";
            echo "    Templates:\n";

            foreach ($res['rep'] as $tpl) {
                $tpl_id = $tpl['id'] ?? '?';
                $tpl_name = $tpl['n'] ?? '???';
                echo "      - Template $tpl_id: $tpl_name\n";
            }
            echo "\n";
        }
    }

    echo "\n=== CHERCHER TEMPLATE 1 (VOYAGE) ===\n";
    foreach ($data['items'] as $res) {
        $res_id = $res['id'] ?? '?';
        $res_name = $res['nm'] ?? '?';

        if (isset($res['rep'])) {
            foreach ($res['rep'] as $tpl) {
                $tpl_name = $tpl['n'] ?? '';
                if (strpos(strtoupper($tpl_name), 'VOYAGE') !== false ||
                    strpos(strtoupper($tpl_name), 'ETENDU') !== false) {
                    echo "Resource ID: $res_id | Nom: $res_name | Template: $tpl_name\n";
                }
            }
        }
    }

    echo "\n=== CHERCHER TEMPLATE KILOMETRAGE ===\n";
    foreach ($data['items'] as $res) {
        $res_id = $res['id'] ?? '?';
        $res_name = $res['nm'] ?? '?';

        if (isset($res['rep'])) {
            foreach ($res['rep'] as $tpl) {
                $tpl_name = $tpl['n'] ?? '';
                if (strpos(strtoupper($tpl_name), 'KILOMETRAGE') !== false) {
                    echo "Resource ID: $res_id | Nom: $res_name | Template: $tpl_name\n";
                }
            }
        }
    }
}

echo "\n=== ✅ FIN ===\n";
echo "</pre>";
?>
