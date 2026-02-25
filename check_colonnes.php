<?php
require_once 'config.php';
$pdo = getDB();

echo "<h1>🔍 Vérification de la table éco-conduite</h1>";

echo "<h2>Toutes les colonnes (avec leurs vrais noms) :</h2>";
$stmt = $pdo->query("DESCRIBE `éco-conduite`");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<ul>";
foreach ($cols as $col) {
    echo "<li><code>" . htmlspecialchars($col['Field']) . "</code></li>";
}
echo "</ul>";

echo "<h2>Premières données (pour voir ce qui s'affiche) :</h2>";
$stmt = $pdo->query("SELECT * FROM `éco-conduite` LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    echo "<table border='1' cellpadding='10' style='width:100%'>";
    foreach ($row as $colonne => $valeur) {
        echo "<tr>";
        echo "<td style='font-weight:bold; background:#f0f0f0'>" . htmlspecialchars($colonne) . "</td>";
        echo "<td><code>" . htmlspecialchars($valeur ?? 'NULL') . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";
}
?>