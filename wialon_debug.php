<?php
/**
 * WIALON DEBUG VERSION
 * Shows exactly what's being sent to the API
 */

require_once 'config.php';

class WialonDebug {
    private $token;
    private $base_url = 'https://hst-api.wialon.com/wialon/ajax.html';
    private $sid = null;
    private $sid_timestamp = null;
    
    public function __construct() {
        $this->token = getenv('WIALON_API_TOKEN');
        if (!$this->token) {
            throw new Exception("WIALON_API_TOKEN not set");
        }
        echo "✅ Token loaded: " . substr($this->token, 0, 10) . "...\n\n";
    }
    
    /**
     * Step 1: Login
     */
    public function testLogin() {
        echo "=" . str_repeat("=", 48) . "\n";
        echo "STEP 1: TOKEN/LOGIN\n";
        echo "=" . str_repeat("=", 48) . "\n\n";
        
        $params = ['token' => $this->token];
        $params_json = json_encode($params);
        $params_encoded = urlencode($params_json);
        
        $url = $this->base_url . '?svc=token/login&params=' . $params_encoded;
        
        echo "📋 Request Details:\n";
        echo "Method: GET\n";
        echo "Service: token/login\n";
        echo "URL (first 100 chars): " . substr($url, 0, 100) . "...\n";
        echo "Params JSON: " . $params_json . "\n\n";
        
        echo "🔐 Sending login request...\n\n";
        
        $response = $this->curlRequest($url);
        
        if (!isset($response['gis_sid'])) {
            echo "❌ Login failed!\n";
            echo "Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n";
            return false;
        }
        
        $this->sid = $response['gis_sid'];
        $this->sid_timestamp = time();
        
        echo "✅ Login successful!\n";
        echo "SID: " . $this->sid . "\n";
        echo "SID Timestamp: " . date('Y-m-d H:i:s', $this->sid_timestamp) . "\n";
        echo "SID Lifetime: 20 minutes\n";
        echo "Will expire at: " . date('Y-m-d H:i:s', $this->sid_timestamp + 1200) . "\n\n";
        
        return true;
    }
    
    /**
     * Step 2: Get Unit Groups
     */
    public function testGetUnitGroups() {
        echo "=" . str_repeat("=", 48) . "\n";
        echo "STEP 2: CORE/SEARCH_ITEMS (Unit Groups)\n";
        echo "=" . str_repeat("=", 48) . "\n\n";
        
        if (!$this->sid) {
            echo "❌ No SID available. Run testLogin() first.\n";
            return false;
        }
        
        // Calculate SID age
        $sid_age = time() - $this->sid_timestamp;
        echo "📊 SID Status:\n";
        echo "   Age: {$sid_age} seconds\n";
        echo "   Remaining: " . (1200 - $sid_age) . " seconds\n";
        if ($sid_age > 1200) {
            echo "   ⚠️ WARNING: SID has expired!\n";
        }
        echo "\n";
        
        $params = [
            'spec' => [
                'itemsType' => 'avl_unit_group',
                'propName' => 'sys_name',
                'propValueMask' => '*',
                'sortType' => 'sys_name',
                'propType' => 'property'
            ],
            'force' => 1,
            'flags' => 1,
            'from' => 0,
            'to' => 0
        ];
        
        $params_json = json_encode($params);
        $params_encoded = urlencode($params_json);
        
        $url = $this->base_url . '?svc=core/search_items&params=' . $params_encoded . '&sid=' . $this->sid;
        
        echo "📋 Request Details:\n";
        echo "Method: GET\n";
        echo "Service: core/search_items\n";
        echo "SID in URL: " . substr($this->sid, 0, 10) . "...\n";
        echo "URL (first 150 chars): " . substr($url, 0, 150) . "...\n";
        echo "Params JSON:\n" . json_encode($params, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
        
        echo "📦 Fetching unit groups...\n\n";
        
        $response = $this->curlRequest($url);
        
        if (isset($response['error'])) {
            echo "❌ Error in response!\n";
            echo "Error Code: " . $response['error'] . "\n";
            echo "Full Response: " . json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            
            // Error code interpretation
            switch ($response['error']) {
                case 1:
                    echo "⚠️ Error 1: Invalid SID or SID expired\n";
                    echo "   Try getting a fresh SID\n";
                    break;
                case 4:
                    echo "⚠️ Error 4: Invalid parameters\n";
                    echo "   Check the params JSON structure\n";
                    break;
                case 3:
                    echo "⚠️ Error 3: Access denied\n";
                    echo "   Check account permissions\n";
                    break;
                default:
                    echo "⚠️ Unknown error code: " . $response['error'] . "\n";
            }
            echo "\n";
            return false;
        }
        
        if (empty($response['items'])) {
            echo "⚠️ No groups found (empty response)\n\n";
            return false;
        }
        
        echo "✅ Got unit groups!\n";
        echo "Count: " . count($response['items']) . "\n\n";
        
        foreach ($response['items'] as $item) {
            echo "  📍 {$item['nm']} (ID: {$item['id']})\n";
        }
        echo "\n";
        
        return true;
    }
    
    /**
     * Step 3: Get Units in a Group
     */
    public function testGetUnitsInGroup($groupId = 1) {
        echo "=" . str_repeat("=", 48) . "\n";
        echo "STEP 3: GET UNITS IN GROUP\n";
        echo "=" . str_repeat("=", 48) . "\n\n";
        
        if (!$this->sid) {
            echo "❌ No SID available\n";
            return false;
        }
        
        $params = [
            'spec' => [
                'itemsType' => 'avl_unit',
                'groupId' => $groupId,
                'propName' => 'sys_name',
                'propValueMask' => '*',
                'sortType' => 'sys_name',
                'propType' => 'property'
            ],
            'force' => 1,
            'flags' => 1,
            'from' => 0,
            'to' => 100
        ];
        
        $params_json = json_encode($params);
        $params_encoded = urlencode($params_json);
        
        $url = $this->base_url . '?svc=core/search_items&params=' . $params_encoded . '&sid=' . $this->sid;
        
        echo "📋 Fetching units in group {$groupId}...\n";
        echo "URL length: " . strlen($url) . " chars\n";
        echo "Params: " . $params_json . "\n\n";
        
        $response = $this->curlRequest($url);
        
        if (isset($response['error'])) {
            echo "❌ Error: " . $response['error'] . "\n";
            echo json_encode($response, JSON_PRETTY_PRINT) . "\n\n";
            return false;
        }
        
        if (empty($response['items'])) {
            echo "⚠️ No units in group\n\n";
            return false;
        }
        
        echo "✅ Got units in group!\n";
        echo "Count: " . count($response['items']) . "\n\n";
        
        foreach (array_slice($response['items'], 0, 5) as $item) {
            echo "  🚗 {$item['nm']} (ID: {$item['id']})\n";
        }
        if (count($response['items']) > 5) {
            echo "  ... and " . (count($response['items']) - 5) . " more\n";
        }
        echo "\n";
        
        return true;
    }
    
    /**
     * CURL wrapper with debugging
     */
    private function curlRequest($url, $timeout = 15) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_USERAGENT => 'WialonDebug/1.0'
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            echo "❌ CURL Error: $curl_error\n";
            return ['error' => 'curl_error', 'message' => $curl_error];
        }
        
        if ($http_code !== 200) {
            echo "❌ HTTP Error: $http_code\n";
            return ['error' => 'http_error', 'code' => $http_code];
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "❌ JSON Error: " . json_last_error_msg() . "\n";
            echo "Response: " . substr($response, 0, 200) . "\n";
            return ['error' => 'json_error', 'message' => json_last_error_msg()];
        }
        
        return $data;
    }
    
    /**
     * Run all tests
     */
    public function runAll() {
        echo "\n\n";
        echo "╔" . str_repeat("=", 48) . "╗\n";
        echo "║" . "  WIALON API DEBUG - COMPLETE TEST  " . str_repeat(" ", 11) . "║\n";
        echo "╚" . str_repeat("=", 48) . "╝\n\n";
        
        // Test 1: Login
        if (!$this->testLogin()) {
            echo "⛔ Cannot proceed without valid SID\n";
            return false;
        }
        
        sleep(1);
        
        // Test 2: Get groups
        if (!$this->testGetUnitGroups()) {
            echo "⛔ Cannot get groups. Check SID and parameters.\n";
            return false;
        }
        
        sleep(1);
        
        // Test 3: Get units (in first group)
        $this->testGetUnitsInGroup(1);
        
        echo "=" . str_repeat("=", 48) . "\n";
        echo "✅ DEBUG TEST COMPLETE\n";
        echo "=" . str_repeat("=", 48) . "\n\n";
        
        return true;
    }
}

// ============================================
// EXECUTION
// ============================================

try {
    $debug = new WialonDebug();
    $debug->runAll();
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}

?>