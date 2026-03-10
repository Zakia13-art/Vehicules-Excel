<?php
$dataDir = '/Users/mbelmahi/Project/well/experience/Vehicules-Excel/data/entreprises/';

echo "Directory: " . $dataDir . "\n\n";

// List all xlsx files
echo "All xlsx files:\n";
$allFiles = glob($dataDir . '*.xlsx');
print_r($allFiles);

// Try eco pattern
echo "\n\nEco pattern (*Éco-conduite*.xlsx):\n";
$filesEco = glob($dataDir . '*Éco-conduite*.xlsx');
print_r($filesEco);

// Try kilo pattern
echo "\n\nKilo pattern (*Kilométrage*.xlsx):\n";
$filesKilo = glob($dataDir . '*Kilométrage*.xlsx');
print_r($filesKilo);

// Try with wildcards only
echo "\n\nUsing simpler patterns:\n";
$filesEcoSimple = glob($dataDir . '*co*.xlsx');
$filesKiloSimple = glob($dataDir . '*Kilo*.xlsx');
echo "Files with 'co': " . count($filesEcoSimple) . "\n";
print_r($filesEcoSimple);
echo "\nFiles with 'Kilo': " . count($filesKiloSimple) . "\n";
print_r($filesKiloSimple);
?>
