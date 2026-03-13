<?php
/**
 * debug_complet.php — Diagnostic ULTRA-détaillé
 */

echo '<h1>🔍 Diagnostic Ultra-Détaillé</h1>';
echo '<style>
body { font-family: Arial; padding: 20px; background: #f5f5f5; }
.box { background: white; padding: 15px; margin: 15px 0; border-radius: 8px; border-left: 4px solid #3498db; }
code { background: #ecf0f1; padding: 5px 10px; border-radius: 3px; font-family: monospace; }
pre { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 5px; overflow-x: auto; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
table th, table td { padding: 10px; border: 1px solid #ddd; text-align: left; }
table th { background: #ecf0f1; }
.success { border-left-color: #27ae60; }
.error { border-left-color: #e74c3c; }
</style>';

$dataDir = 'C:/xampp/htdocs/vehicules/data/entreprises/';

echo '<div class="box success">';
echo '<h2>1️⃣ Vérification du dossier</h2>';

if (!is_dir($dataDir)) {
    echo '<p style="color: red;"><strong>❌ ERREUR : Le dossier N\'EXISTE PAS !</strong></p>';
    echo '<p>Chemin cherché : <code>' . htmlspecialchars($dataDir) . '</code></p>';
    die();
}

echo '<p style="color: green;"><strong>✅ Le dossier EXISTE</strong></p>';
echo '</div>';

echo '<div class="box">';
echo '<h2>2️⃣ Liste de TOUS les fichiers du dossier</h2>';

$allFiles = scandir($dataDir);
$allFiles = array_diff($allFiles, ['.', '..']);

if (empty($allFiles)) {
    echo '<p style="color: red;"><strong>❌ Le dossier est VIDE !</strong></p>';
    die();
}

echo '<table>';
echo '<tr><th>Nom du fichier</th><th>Extension</th><th>Contient "Éco"?</th><th>Contient "Kilomé"?</th></tr>';

foreach ($allFiles as $file) {
    $fullPath = $dataDir . $file;
    if (!is_file($fullPath)) continue;
    
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    $contienEco = (strpos(strtolower($file), 'éco') !== false || strpos(strtolower($file), 'eco') !== false) ? '✅ OUI' : '❌ NON';
    $contienKilo = (strpos(strtolower($file), 'kilomé') !== false || strpos(strtolower($file), 'om') !== false) ? '✅ OUI' : '❌ NON';
    
    echo '<tr>';
    echo '<td><code style="font-size:11px;">' . htmlspecialchars($file) . '</code></td>';
    echo '<td>' . htmlspecialchars($ext) . '</td>';
    echo '<td>' . $contienEco . '</td>';
    echo '<td>' . $contienKilo . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

echo '<div class="box">';
echo '<h2>3️⃣ Test des patterns glob()</h2>';

$patterns = [
    '*Éco-conduite*.xlsx' => 'Cherche fichiers avec "Éco-conduite"',
    '*eco-conduite*.xlsx' => 'Cherche fichiers avec "eco-conduite"',
    '*éco-conduite*.xlsx' => 'Cherche fichiers avec "éco-conduite"',
    '*Éco*.xlsx' => 'Cherche fichiers avec "Éco"',
    '*eco*.xlsx' => 'Cherche fichiers avec "eco"',
    '*Kilomé*.xlsx' => 'Cherche fichiers avec "Kilomé"',
    '*om*.xlsx' => 'Cherche fichiers avec "om"',
    '*.xlsx' => 'Cherche TOUS les fichiers .xlsx',
];

echo '<table>';
echo '<tr><th>Pattern</th><th>Résultat</th><th>Nombre</th></tr>';

foreach ($patterns as $pattern => $desc) {
    $files = glob($dataDir . $pattern);
    $nb = count($files);
    $couleur = $nb > 0 ? '#d5f4e6' : '#fadbd8';
    
    echo '<tr style="background: ' . $couleur . ';">';
    echo '<td><code>' . htmlspecialchars($pattern) . '</code><br><small>' . $desc . '</small></td>';
    echo '<td>';
    if ($nb > 0) {
        echo '<strong style="color: green;">✅ TROUVÉ</strong>';
        foreach ($files as $f) {
            echo '<br><small>' . htmlspecialchars(basename($f)) . '</small>';
        }
    } else {
        echo '<strong style="color: red;">❌ AUCUN</strong>';
    }
    echo '</td>';
    echo '<td>' . $nb . '</td>';
    echo '</tr>';
}
echo '</table>';
echo '</div>';

echo '<div class="box">';
echo '<h2>4️⃣ Code PHP à utiliser</h2>';

// Trouver le bon pattern
$bestPattern = '';
$bestFiles = [];

foreach (['*Éco-conduite*.xlsx', '*eco-conduite*.xlsx', '*Éco*.xlsx', '*eco*.xlsx'] as $p) {
    $f = glob($dataDir . $p);
    if (!empty($f)) {
        $bestPattern = $p;
        $bestFiles = $f;
        break;
    }
}

if (!empty($bestFiles)) {
    echo '<p><strong>✅ Pattern qui fonctionne :</strong></p>';
    echo '<pre>$filesEco = glob($dataDir . \'' . htmlspecialchars($bestPattern) . '\');</pre>';
    
    echo '<p><strong>Fichiers trouvés :</strong></p>';
    echo '<ul>';
    foreach ($bestFiles as $f) {
        echo '<li><code>' . htmlspecialchars(basename($f)) . '</code></li>';
    }
    echo '</ul>';
} else {
    echo '<p style="color: red;"><strong>❌ Aucun pattern ne fonctionne !</strong></p>';
}

echo '</div>';

echo '<div class="box error">';
echo '<h2>5️⃣ Solution</h2>';
echo '<p>Utilise ce code dans ton fichier PHP :</p>';
echo '<pre>';
echo '$dataDir = \'C:/xampp/htdocs/vehicules/data/entreprises/\';' . "\n\n";
echo '// Chercher les fichiers Éco-conduite' . "\n";
echo '$filesEco = glob($dataDir . \'*Éco*.xlsx\');' . "\n";
echo 'if (empty($filesEco)) {' . "\n";
echo '    $filesEco = glob($dataDir . \'*eco*.xlsx\');' . "\n";
echo '}' . "\n\n";
echo '// Chercher le fichier Kilométrage' . "\n";
echo '$fileKilo = glob($dataDir . \'*Kilométrage*.xlsx\');' . "\n";
echo 'if (empty($fileKilo)) {' . "\n";
echo '    $fileKilo = glob($dataDir . \'*om*.xlsx\');' . "\n";
echo '}' . "\n";
echo '$fileKilo = !empty($fileKilo) ? $fileKilo[0] : null;' . "\n";
echo '</pre>';
echo '</div>';

?>