<?php
/**
 * getitemid.php — Multi-Sociétés
 * Récupère les données de TOUTES les sociétés dans le dossier
 */

require_once 'config.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

class ItemGetter {
    private $dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';
    private $SHEET_ECO = 'Éco-conduite';
    private $SHEET_KILO = 'Kilométrage+Heures moteur';

    /**
     * Lire une feuille Excel
     */
    public function readExcelSheet($filePath, $sheetName) {
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getSheetByName($sheetName) ?? $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
            if (empty($rows)) return [];
            $headers = array_map(fn($h) => trim((string)($h ?? '')), array_shift($rows));
            $data = [];
            foreach ($rows as $row) {
                if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) continue;
                $assoc = [];
                foreach ($headers as $i => $h) {
                    $assoc[$h] = trim((string)($row[$i] ?? ''));
                }
                $data[] = $assoc;
            }
            return $data;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Obtenir TOUS les véhicules de TOUTES les sociétés
     */
    public function getAllVehicles($societyFilter = null) {
        $allVehicles = [];
        
        if (!is_dir($this->dataDir)) return [];
        
        $items = @scandir($this->dataDir);
        if ($items === false) return [];
        
        // Grouper par société
        $societyFiles = [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $filepath = $this->dataDir . $item;
            if (!is_file($filepath)) continue;
            
            $societyName = $this->extractSocietyName($item);
            if (!isset($societyFiles[$societyName])) {
                $societyFiles[$societyName] = [];
            }
            
            if (strpos(strtolower($item), 'conduite') !== false) {
                $societyFiles[$societyName]['eco'] = $filepath;
            } elseif (strpos(strtolower($item), 'kilo') !== false || strpos(strtolower($item), 'om') !== false) {
                $societyFiles[$societyName]['kilo'] = $filepath;
            }
        }
        
        // Traiter chaque société
        foreach ($societyFiles as $society => $files) {
            if (!isset($files['eco']) || !isset($files['kilo'])) continue;
            
            // Appliquer le filtre société si demandé
            if ($societyFilter && $societyFilter !== $society) continue;
            
            $ecoData = $this->readExcelSheet($files['eco'], $this->SHEET_ECO);
            $kiloData = $this->readExcelSheet($files['kilo'], $this->SHEET_KILO);

            $ecoMap = [];
            foreach ($ecoData as $row) {
                $reg = $row['Regroupement'] ?? '';
                if ($reg === '') continue;
                if (!isset($ecoMap[$reg])) {
                    $ecoMap[$reg] = ['evaluation' => 0.0, 'infractions' => 0];
                }
                $evalNum = (float) preg_replace('/[^0-9.]/', '', $row['Évaluation'] ?? '0');
                if ($evalNum > $ecoMap[$reg]['evaluation']) {
                    $ecoMap[$reg]['evaluation'] = $evalNum;
                }
                if (($row['Infraction'] ?? '') !== '' && ($row['Infraction'] ?? '') !== '-----') {
                    $ecoMap[$reg]['infractions']++;
                }
            }

            foreach ($kiloData as $row) {
                $reg = $row['Regroupement'] ?? '';
                $info = $ecoMap[$reg] ?? null;
                if ($reg === '' || $info === null) continue;
                $km = (float) preg_replace('/[^0-9.]/', '', $row['Kilométrage'] ?? '0');
                $note = $info['evaluation'] / 10;
                $allVehicles[] = [
                    'id' => md5($society . '_' . $reg),
                    'name' => $reg,
                    'society' => $society,
                    'note' => round($note * 100),
                    'alertes' => $info['infractions'],
                    'km' => $km,
                    'km_text' => $row['Kilométrage'] ?? '0 km',
                    'duree' => $row['Durée'] ?? '—',
                ];
            }
        }
        
        return $allVehicles;
    }

    /**
     * Obtenir les sociétés disponibles
     */
    public function getSocieties() {
        $societies = [];
        
        if (!is_dir($this->dataDir)) return [];
        
        $items = @scandir($this->dataDir);
        if ($items === false) return [];
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $filepath = $this->dataDir . $item;
            if (!is_file($filepath)) continue;
            
            $society = $this->extractSocietyName($item);
            if (!in_array($society, $societies)) {
                $societies[] = $society;
            }
        }
        
        sort($societies);
        return $societies;
    }

    /**
     * Extraire le nom de la société
     */
    private function extractSocietyName($filename) {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/[\s\-_]+(co-conduite|conduite|kilo|kilometer|kilométrage|heures|moteur|om).*/i', '', $name);
        return trim($name) ?: 'Non identifiée';
    }

    /**
     * Obtenir les informations de TOUS les fichiers
     */
    public function getFileInfo($societyFilter = null) {
        $files = [];
        if (!is_dir($this->dataDir)) return $files;
        
        $items = @scandir($this->dataDir);
        if ($items === false) return $files;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $filepath = $this->dataDir . $item;
            if (is_file($filepath) && strpos(strtolower($item), '.xlsx') !== false) {
                $societyName = $this->extractSocietyName($item);
                
                if ($societyFilter && $societyFilter !== $societyName) continue;
                
                $type = 'Source';
                $icon = '📄';
                if (strpos(strtolower($item), 'conduite') !== false) {
                    $type = 'Éco-conduite';
                    $icon = '📊';
                } elseif (strpos(strtolower($item), 'kilo') !== false || strpos(strtolower($item), 'om') !== false) {
                    $type = 'Kilométrage';
                    $icon = '📍';
                }
                
                $files[] = [
                    'name' => $item,
                    'size' => filesize($filepath),
                    'modified' => date('d/m/Y H:i', filemtime($filepath)),
                    'path' => $filepath,
                    'type' => $type,
                    'icon' => $icon,
                    'society' => $societyName,
                ];
            }
        }
        return $files;
    }

    /**
     * Obtenir un véhicule par ID
     */
    public function getItemById($itemId) {
        $vehicles = $this->getAllVehicles();
        foreach ($vehicles as $v) {
            if ($v['id'] === $itemId) return $v;
        }
        return null;
    }

    /**
     * Obtenir les détails d'un véhicule
     */
    public function getVehicleDetails($vehicleName, $society) {
        $fileInfo = $this->getFileInfo($society);
        
        $ecoFile = null;
        $kiloFile = null;
        
        foreach ($fileInfo as $f) {
            if (strpos(strtolower($f['type']), 'éco') !== false) {
                $ecoFile = $f['path'];
            } elseif (strpos(strtolower($f['type']), 'kiló') !== false || strpos(strtolower($f['type']), 'kilo') !== false) {
                $kiloFile = $f['path'];
            }
        }
        
        if (!$ecoFile || !$kiloFile) return null;
        
        $ecoData = $this->readExcelSheet($ecoFile, $this->SHEET_ECO);
        $kiloData = $this->readExcelSheet($kiloFile, $this->SHEET_KILO);
        
        $ecoInfo = null;
        foreach ($ecoData as $row) {
            if (($row['Regroupement'] ?? '') === $vehicleName) {
                $ecoInfo = $row;
                break;
            }
        }
        
        $kiloInfo = null;
        foreach ($kiloData as $row) {
            if (($row['Regroupement'] ?? '') === $vehicleName) {
                $kiloInfo = $row;
                break;
            }
        }
        
        return ['name' => $vehicleName, 'society' => $society, 'eco' => $ecoInfo, 'kilo' => $kiloInfo];
    }
}

// API REST
if (isset($_GET['action'])) {
    $getter = new ItemGetter();
    header('Content-Type: application/json; charset=utf-8');
    switch ($_GET['action']) {
        case 'all_vehicles':
            $society = $_GET['society'] ?? null;
            echo json_encode($getter->getAllVehicles($society));
            break;
        case 'societies':
            echo json_encode($getter->getSocieties());
            break;
        case 'get_by_id':
            $id = $_GET['id'] ?? '';
            echo json_encode($getter->getItemById($id) ?? ['error' => 'Non trouvé']);
            break;
        case 'get_details':
            $name = $_GET['name'] ?? '';
            $society = $_GET['society'] ?? '';
            echo json_encode($getter->getVehicleDetails($name, $society) ?? ['error' => 'Non trouvé']);
            break;
        case 'file_info':
            $society = $_GET['society'] ?? null;
            echo json_encode($getter->getFileInfo($society));
            break;
        default:
            echo json_encode(['error' => 'Action inconnue']);
    }
    exit;
}

$getter = new ItemGetter();