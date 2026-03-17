<?php
/**
 * 🧪 WIALON API TEST SCRIPT V2
 * Fixed: Uses gis_sid correctly
 */

define('WIALON_TOKEN', 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB');
define('BASE_URL', 'https://hst-api.wialon.com/wialon/ajax.html');

echo "<!DOCTYPE html>";
echo "<html><head><meta charset='utf-8'><style>body{font-family:Arial;margin:20px;}</style></head><body>";

// ✅ STEP 1: LOGIN
echo "<h2>🔐 STEP 1: LOGIN</h2>";
$login_response = makeWialonCall('token/login', array(
    'token' => WIALON_TOKEN
));

echo "Response keys: " . implode(", ", array_keys($login_response)) . "<br>";

if (!isset($login_response['gis_sid'])) {
    echo "❌ No gis_sid found!<br>";
    echo "<pre>" . json_encode($login_response, JSON_PRETTY_PRINT) . "</pre>";
    die();
}

$sid = $login_response['gis_sid'];
$user_name = $login_response['user']['nm'] ?? 'Unknown';

echo "✅ LOGIN SUCCESS!<br>";
echo "📌 SID (gis_sid): <strong>$sid</strong><br>";
echo "👤 User: <strong>$user_name</strong><br>";

// ✅ STEP 2: LIST ALL UNIT GROUPS
echo "<h2>📦 STEP 2: SEARCH UNIT GROUPS</h2>";

$search_params = array(
    'spec' => array(
        'itemsType' => 'avl_unit_group',
        'propName' => 'sys_name',
        'propValueMask' => '*',  // Search all
        'sortType' => 'sys_name',
        'propType' => 'property'
    ),
    'force' => 1,
    'flags' => 1,
    'from' => 0,
    'to' => 100
);

$search_response = makeWialonCall('core/search_items', $search_params, $sid);

if (isset($search_response['error'])) {
    echo "❌ Search error: " . $search_response['error'] . "<br>";
} else {
    $total_count = $search_response['totalItemsCount'] ?? 0;
    echo "Found: <strong>$total_count</strong> unit groups<br>";
    
    if ($total_count > 0 && isset($search_response['items'])) {
        echo "<table border='1' style='margin-top:10px;'>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        
        foreach ($search_response['items'] as $item) {
            $group_id = $item['id'];
            $group_name = $item['nm'];
            echo "<tr>";
            echo "<td>$group_id</td>";
            echo "<td>$group_name</td>";
            echo "</tr>";
        }
        echo "</table>";
        
        // ✅ STEP 3: Try with first group
        echo "<h2>📊 STEP 3: EXECUTE REPORT (first group)</h2>";
        
        $first_group_id = $search_response['items'][0]['id'];
        $first_group_name = $search_response['items'][0]['nm'];
        
        echo "Using group: <strong>$first_group_name</strong> (ID: $first_group_id)<br>";
        
        $to = time();
        $from = $to - (7 * 86400);
        
        $report_params = array(
            'reportResourceId' => 19907460,
            'reportTemplateId' => 1,
            'reportObjectId' => $first_group_id,
            'reportObjectSecId' => 0,
            'interval' => array(
                'from' => $from,
                'to' => $to,
                'flags' => 0
            )
        );
        
        $report_response = makeWialonCall('report/exec_report', $report_params, $sid);
        
        if (isset($report_response['error'])) {
            echo "❌ Report error: " . $report_response['error'] . "<br>";
            echo "<pre>" . json_encode($report_response, JSON_PRETTY_PRINT) . "</pre>";
        } else {
            echo "✅ Report executed!<br>";
            
            if (isset($report_response['reportResult']['tables'])) {
                $tables_count = count($report_response['reportResult']['tables']);
                echo "Tables: <strong>$tables_count</strong><br>";
                
                foreach ($report_response['reportResult']['tables'] as $table_idx => $table) {
                    $rows_count = isset($table['rows']) ? count($table['rows']) : 0;
                    echo "  - Table $table_idx: $rows_count rows<br>";
                }
                
                echo "<pre>" . json_encode($report_response, JSON_PRETTY_PRINT) . "</pre>";
            } else {
                echo "⚠️ No tables in response<br>";
                echo "<pre>" . json_encode($report_response, JSON_PRETTY_PRINT) . "</pre>";
            }
        }
    } else {
        echo "❌ No groups found<br>";
    }
}

echo "</body></html>";

// ✅ HELPER FUNCTION
function makeWialonCall($service, $params, $sid = null) {
    $url = BASE_URL . '?svc=' . $service;
    
    $params_json = json_encode($params);
    $url .= '&params=' . urlencode($params_json);
    
    if ($sid) {
        $url .= '&sid=' . $sid;
    }
    
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_HTTPHEADER => array(
            "cache-control: no-cache",
            "content-type: application/json"
        ),
        CURLOPT_SSL_VERIFYPEER => false,
    ));
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    
    $decoded = json_decode($response, true);
    return $decoded ?: array('error' => 'Invalid JSON response', 'raw' => $response);
}

?>