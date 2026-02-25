<?php
require_once 'config.php';
$pdo = getDB();

echo "<h2>📊 Diagnostic des Tables</h2>";

// Table 1
echo "<h3>Table: kilométrage+heures_moteur</h3>";
echo "<table border='1' cellpadding='5'>";
$stmt = $pdo->query("SELECT * FROM `kilométrage+heures_moteur` LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($rows)) {
    echo "<tr>";
    foreach (array_keys($rows[0]) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
}
echo "</table>";

// Table 2
echo "<h3>Table: éco-conduite</h3>";
echo "<table border='1' cellpadding='5'>";
$stmt = $pdo->query("SELECT * FROM `éco-conduite` LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!empty($rows)) {
    echo "<tr>";
    foreach (array_keys($rows[0]) as $col) {
        echo "<th>" . htmlspecialchars($col) . "</th>";
    }
    echo "</tr>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $val) {
            echo "<td>" . htmlspecialchars($val) . "</td>";
        }
        echo "</tr>";
    }
}
echo "</table>";

// Vérifier les Regroupements
echo "<h3>Vérification des Regroupements</h3>";
echo "<p><strong>kilométrage+heures_moteur:</strong></p>";
$stmt = $pdo->query("SELECT DISTINCT `Regroupement` FROM `kilométrage+heures_moteur`");
$regs1 = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($regs1 as $r) {
    echo "- " . htmlspecialchars($r) . "<br>";
}

echo "<p><strong>éco-conduite:</strong></p>";
$stmt = $pdo->query("SELECT DISTINCT `Regroupement` FROM `éco-conduite`");
$regs2 = $stmt->fetchAll(PDO::FETCH_COLUMN);
foreach ($regs2 as $r) {
    echo "- " . htmlspecialchars($r) . "<br>";
}
?>