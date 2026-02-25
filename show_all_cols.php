<?php
require_once 'config.php';
$pdo = getDB();

echo "<h1>📊 TOUTES les colonnes de éco-conduite</h1>";

$stmt = $pdo->query("SHOW COLUMNS FROM `éco-conduite`");
$cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table border='1' cellpadding='10' style='width:100%'>";
echo "<tr><th>Numéro</th><th>Nom de la colonne</th><th>Type</th></tr>";
$i = 1;
foreach ($cols as $col) {
    echo "<tr>";
    echo "<td><strong>$i</strong></td>";
    echo "<td><code style='background:#f0f0f0; padding:5px'>" . htmlspecialchars($col['Field']) . "</code></td>";
    echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
    echo "</tr>";
    $i++;
}
echo "</table>";

echo "<p><strong>Total : " . count($cols) . " colonnes</strong></p>";
?>