<?php
require_once 'config.php';
require_once 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

$dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';

echo "<h1>Diagnostic des fichiers Excel</h1>";
echo "<hr>";

// Vérifier les fichiers
$filesEco = glob($dataDir . '*co-conduite*.xlsx');
$filesEco = array_filter($filesEco, fn($f) => strpos(basename($f), '~$') === false);
$filesKilo = glob($dataDir . '*Kilom*.xlsx');
$fileKilo = !empty($filesKilo) ? $filesKilo[0] : null;

echo "<h2>Fichiers trouvés :</h2>";
echo "<p><strong>Fichiers ECO (co-conduite) :</strong></p>";
foreach ($filesEco as $f) {
    echo "- " . basename($f) . "<br>";
}

echo "<p><strong>Fichier Kilométrage :</strong></p>";
echo $fileKilo ? basename($fileKilo) : "AUCUN FICHIER TROUVÉ";
echo "<hr>";

// Fonction de lecture
function readExcelBySheetName(string $filePath, string $searchName): array {
    try {
        $spreadsheet = IOFactory::load($filePath);
        $sheet = null;
        foreach ($spreadsheet->getSheetNames() as $name) {
            if (stripos($name, $searchName) !== false) {
                $sheet = $spreadsheet->getSheetByName($name);
                break;
            }
        }
        if ($sheet === null) $sheet = $spreadsheet->getActiveSheet();
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

// Lire les données
echo "<h2>Données Kilométrage :</h2>";
if ($fileKilo) {
    $kiloData = readExcelBySheetName($fileKilo, 'Kilométrage');
    echo "<p><strong>Nombre de lignes :</strong> " . count($kiloData) . "</p>";
    if (!empty($kiloData)) {
        echo "<p><strong>Colonnes :</strong></p>";
        echo "<pre>";
        print_r(array_keys($kiloData[0]));
        echo "</pre>";
        echo "<p><strong>Première ligne :</strong></p>";
        echo "<pre>";
        print_r($kiloData[0]);
        echo "</pre>";
    }
} else {
    echo "<p style='color: red;'>Fichier Kilométrage non trouvé</p>";
}

echo "<hr>";
echo "<h2>Données Infraction :</h2>";
$infractionData = [];
foreach ($filesEco as $file) {
    if (stripos($file, 'infraction') !== false) {
        $infractionData = array_merge($infractionData, readExcelBySheetName($file, 'rapport'));
    }
}
echo "<p><strong>Nombre de lignes :</strong> " . count($infractionData) . "</p>";
if (!empty($infractionData)) {
    echo "<p><strong>Colonnes :</strong></p>";
    echo "<pre>";
    print_r(array_keys($infractionData[0]));
    echo "</pre>";
    echo "<p><strong>Première ligne :</strong></p>";
    echo "<pre>";
    print_r($infractionData[0]);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Pas de données d'infraction</p>";
}
?>