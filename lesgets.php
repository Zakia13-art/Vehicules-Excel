<?php
/**
 * lesgets.php — Multi-Sociétés
 * Gestion et téléchargement des fichiers de TOUTES les sociétés
 */

require_once 'config.php';
require_once 'vendor/autoload.php';
require_once 'getitemid.php';

class FileManager {
    private $getter;
    private $dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';
    private $downloadDir = 'C:/xampp/htdocs/vehicules/downloads/';

    public function __construct() {
        $this->getter = new ItemGetter();
        if (!is_dir($this->downloadDir)) {
            @mkdir($this->downloadDir, 0755, true);
        }
    }

    /**
     * Lister TOUS les fichiers (multi-sociétés)
     */
    public function listAllFiles($societyFilter = null) {
        $files = [];

        // Fichiers source Excel
        $sourceFiles = $this->getter->getFileInfo($societyFilter);
        foreach ($sourceFiles as $file) {
            $files[] = [
                'id' => md5($file['name']),
                'name' => $file['name'],
                'type' => $file['type'],
                'size' => $this->formatSize($file['size']),
                'size_bytes' => $file['size'],
                'modified' => $file['modified'],
                'category' => 'Source',
                'downloadable' => true,
                'path' => $file['path'],
                'icon' => $file['icon'] ?? '📄',
                'society' => $file['society'],
            ];
        }

        // Fichiers générés
        $generatedFiles = $this->listGeneratedFiles($societyFilter);
        foreach ($generatedFiles as $file) {
            $files[] = $file;
        }

        return $files;
    }

    /**
     * Lister les fichiers générés
     */
    public function listGeneratedFiles($societyFilter = null) {
        $files = [];
        if (!is_dir($this->downloadDir)) return $files;

        $items = @scandir($this->downloadDir);
        if ($items === false) return $files;
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $filepath = $this->downloadDir . $item;
            if (is_file($filepath)) {
                $society = $this->extractSocietyFromFilename($item);
                
                if ($societyFilter && $societyFilter !== $society) continue;
                
                $files[] = [
                    'id' => md5($item),
                    'name' => $item,
                    'type' => pathinfo($item, PATHINFO_EXTENSION),
                    'size' => $this->formatSize(filesize($filepath)),
                    'size_bytes' => filesize($filepath),
                    'modified' => date('d/m/Y H:i', filemtime($filepath)),
                    'category' => 'Généré',
                    'downloadable' => true,
                    'path' => $filepath,
                    'icon' => '📋',
                    'society' => $society,
                ];
            }
        }

        return $files;
    }

    /**
     * Extraire la société du nom de fichier généré
     */
    private function extractSocietyFromFilename($filename) {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        preg_match('/rapport_([^_]+)_/i', $name, $matches);
        return $matches[1] ?? 'Non identifiée';
    }

    /**
     * Télécharger un fichier
     */
    public function downloadFile($filename) {
        $filepath = realpath($this->dataDir . $filename);
        if (!$filepath || !file_exists($filepath) || strpos($filepath, realpath($this->dataDir)) !== 0) {
            $filepath = realpath($this->downloadDir . $filename);
            if (!$filepath || !file_exists($filepath) || strpos($filepath, realpath($this->downloadDir)) !== 0) {
                return false;
            }
        }

        if (!is_readable($filepath)) {
            return false;
        }

        $ext = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        $mimeTypes = [
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'csv' => 'text/csv',
            'pdf' => 'application/pdf',
            'html' => 'text/html',
        ];

        $mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
        header('Content-Length: ' . filesize($filepath));
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($filepath);
        exit;
    }

    /**
     * Supprimer un fichier généré
     */
    public function deleteFile($filename) {
        $filepath = realpath($this->downloadDir . $filename);
        if (!$filepath || !file_exists($filepath) || strpos($filepath, realpath($this->downloadDir)) !== 0) {
            return false;
        }
        return @unlink($filepath);
    }

    /**
     * Formater la taille
     */
    private function formatSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        return round($bytes, 2) . ' ' . $units[$pow];
    }

    /**
     * Obtenir les statistiques
     */
    public function getStatistics($societyFilter = null) {
        $files = $this->listAllFiles($societyFilter);
        $stats = [
            'total_files' => count($files),
            'source_files' => count(array_filter($files, fn($f) => $f['category'] === 'Source')),
            'generated_files' => count(array_filter($files, fn($f) => $f['category'] === 'Généré')),
            'total_size' => 0,
            'total_size_formatted' => '0 B',
        ];

        foreach ($files as $file) {
            $stats['total_size'] += $file['size_bytes'];
        }

        $stats['total_size_formatted'] = $this->formatSize($stats['total_size']);
        return $stats;
    }

    /**
     * Obtenir les sociétés
     */
    public function getSocieties() {
        return $this->getter->getSocieties();
    }
}

if (isset($_GET['action'])) {
    $manager = new FileManager();
    switch ($_GET['action']) {
        case 'list_all':
            header('Content-Type: application/json; charset=utf-8');
            $society = $_GET['society'] ?? null;
            echo json_encode($manager->listAllFiles($society));
            break;
        case 'download':
            $file = $_GET['file'] ?? '';
            if (!$manager->downloadFile(basename($file))) {
                http_response_code(404);
                echo 'Fichier non trouvé';
            }
            break;
        case 'delete':
            header('Content-Type: application/json; charset=utf-8');
            $file = $_GET['file'] ?? '';
            echo json_encode(['success' => $manager->deleteFile(basename($file))]);
            break;
        case 'stats':
            header('Content-Type: application/json; charset=utf-8');
            $society = $_GET['society'] ?? null;
            echo json_encode($manager->getStatistics($society));
            break;
        case 'societies':
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($manager->getSocieties());
            break;
        default:
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Action inconnue']);
    }
    exit;
}

$manager = new FileManager();