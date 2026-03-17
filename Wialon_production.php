<?php
/**
 * 🚀 WIALON PRODUCTION INTEGRATION
 * Auto-handles SID expiration, retries, and complete workflow
 */

class WialonAPI {
    private $token = 'b6db68331b4b6ed14b61dbfeeaad9a0631CC23ABEEBB9CE43FB28DC0D4A13766308C1CFB';
    private $base_url = 'https://hst-api.wialon.com/wialon/ajax.html';
    private $sid = null;
    private $sid_timestamp = null;
    private $sid_lifetime = 1200; // 20 minutes (conservative)
    
    // Database mappings
    private $group_mapping = [
        'BOUTCHRAFINE' => 1,
        'SOMATRIN' => 2,
        'SONATRANS' => 3,
        'STAR' => 4,
        'SOREP' => 5,
        'RR' => 6,
        'JATRANS' => 7,
        'STTT' => 8,
        'STIR' => 9,
        'ECO' => 10
    ];
    
    /**
     * Ensure SID is valid, refresh if expired
     */
    public function ensureSID() {
        if ($this->sid === null || time() - $this->sid_timestamp > $this->sid_lifetime) {
            $this->login();
        }
        return $this->sid;
    }
    
    /**
     * Login and get new SID
     */
    public function login() {
        echo "[" . date('H:i:s') . "] 🔐 Logging in...\n";
        
        $params = ['token' => $this->token];
        $url = $this->base_url . '?svc=token/login&params=' . urlencode(json_encode($params));
        
        $response = $this->curl_request($url);
        
        if (!isset($response['gis_sid'])) {
            throw new Exception("Login failed: " . json_encode($response));
        }
        
        $this->sid = $response['gis_sid'];
        $this->sid_timestamp = time();
        
        echo "[" . date('H:i:s') . "] ✅ Login successful. SID: {$this->sid}\n";
        return $this->sid;
    }
    
    /**
     * Generic API call with auto-retry on SID expiration
     */
    private function api_call($service, $params, $max_retries = 2) {
        $retries = 0;
        
        while ($retries < $max_retries) {
            $this->ensureSID();
            
            $url = $this->base_url . '?svc=' . $service . '&params=' . urlencode(json_encode($params)) . '&sid=' . $this->sid;
            $response = $this->curl_request($url);
            
            // Check for SID expiration error
            if (isset($response['error']) && $response['error'] == 1) {
                echo "[" . date('H:i:s') . "] ⚠️  SID expired. Retrying...\n";
                $this->sid = null; // Force re-login
                $retries++;
                sleep(1);
                continue;
            }
            
            if (isset($response['error'])) {
                throw new Exception("API error {$response['error']}: " . json_encode($response));
            }
            
            return $response;
        }
        
        throw new Exception("Max retries exceeded for service: $service");
    }
    
    /**
     * Get all unit groups
     */
    public function getUnitGroups() {
        echo "[" . date('H:i:s') . "] 📦 Fetching unit groups...\n";
        
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
        
        $response = $this->api_call('core/search_items', $params);
        
        $groups = [];
        if (!empty($response['items'])) {
            foreach ($response['items'] as $item) {
                if (isset($item['id']) && isset($item['nm'])) {
                    $groups[$item['id']] = $item['nm'];
                    echo "  - {$item['nm']} (ID: {$item['id']})\n";
                }
            }
        }
        
        echo "[" . date('H:i:s') . "] ✅ Found " . count($groups) . " groups\n";
        return $groups;
    }
    
    /**
     * Get units in a specific group
     */
    public function getUnitsInGroup($groupId, $limit = 100) {
        echo "[" . date('H:i:s') . "] 🚗 Fetching units in group $groupId...\n";
        
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
            'to' => $limit
        ];
        
        $response = $this->api_call('core/search_items', $params);
        
        $units = [];
        if (!empty($response['items'])) {
            foreach ($response['items'] as $item) {
                if (isset($item['id']) && isset($item['nm'])) {
                    $units[$item['id']] = $item['nm'];
                    echo "  - {$item['nm']} (ID: {$item['id']})\n";
                }
            }
        }
        
        echo "[" . date('H:i:s') . "] ✅ Found " . count($units) . " units\n";
        return $units;
    }
    
    /**
     * Get trajectory report for a unit
     */
    public function getTrajectory($unitId, $from_time, $to_time, $resourceId = null) {
        echo "[" . date('H:i:s') . "] 📍 Fetching trajectory for unit $unitId...\n";
        
        // Get first resource if not specified
        if ($resourceId === null) {
            $params = [
                'spec' => [
                    'itemsType' => 'avl_resource',
                    'propName' => 'sys_name',
                    'propValueMask' => '*',
                    'sortType' => 'sys_name',
                    'propType' => 'property'
                ],
                'force' => 1,
                'flags' => 1,
                'from' => 0,
                'to' => 1
            ];
            
            $res_response = $this->api_call('core/search_items', $params);
            if (!empty($res_response['items'][0]['id'])) {
                $resourceId = $res_response['items'][0]['id'];
            }
        }
        
        if ($resourceId === null) {
            throw new Exception("No resource found for trajectory report");
        }
        
        // Create report
        $report_params = [
            'reportResourceId' => $resourceId,
            'reportTemplate' => [
                'name' => 'Trip Report',
                'desc' => '',
                'outputFormat' => 'json',
                'columns' => [],
                'filters' => []
            ],
            'reportFilter' => [
                'type' => 'avl_unit',
                'data' => $unitId
            ],
            'eventsFilter' => null,
            'beginDate' => $from_time,
            'endDate' => $to_time
        ];
        
        $response = $this->api_call('report/exec_report', $report_params);
        
        echo "[" . date('H:i:s') . "] ✅ Got trajectory report\n";
        return $response;
    }
    
    /**
     * CURL wrapper with error handling
     */
    private function curl_request($url, $timeout = 15) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("CURL Error: $curl_error");
        }
        
        if ($http_code !== 200) {
            throw new Exception("HTTP Error: $http_code");
        }
        
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("JSON Error: " . json_last_error_msg());
        }
        
        return $data;
    }
}

// ============================================
// MAIN EXECUTION
// ============================================

try {
    $wialon = new WialonAPI();
    
    // Step 1: Login
    $wialon->login();
    
    // Step 2: Get all groups
    $groups = $wialon->getUnitGroups();
    
    // Step 3: For each group, get units
    foreach ($groups as $groupId => $groupName) {
        echo "\n🔄 Processing group: $groupName\n";
        echo str_repeat("-", 50) . "\n";
        
        try {
            $units = $wialon->getUnitsInGroup($groupId, 100);
            
            // Step 4: For each unit, get trajectory
            foreach ($units as $unitId => $unitName) {
                try {
                    // Get today's trajectory (example)
                    $to_time = time();
                    $from_time = $to_time - 86400; // 24 hours ago
                    
                    $trajectory = $wialon->getTrajectory($unitId, $from_time, $to_time);
                    
                    // TODO: Insert into database
                    // set_trajet($unitId, $trajectory, $groupName);
                    
                } catch (Exception $e) {
                    echo "  ❌ Error getting trajectory for $unitName: " . $e->getMessage() . "\n";
                }
            }
        } catch (Exception $e) {
            echo "❌ Error processing group $groupName: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n✅ Complete!\n";
    
} catch (Exception $e) {
    echo "❌ Fatal Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>