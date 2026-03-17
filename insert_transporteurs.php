<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: text/html; charset=utf-8');

require_once("db.php");

?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<style>
		body { font-family: Arial; background: #f5f5f5; padding: 20px; }
		.box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
		h1 { color: #2c3e50; }
		.success { color: #27ae60; font-weight: bold; }
		.error { color: #e74c3c; font-weight: bold; }
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 12px; text-align: left; }
		th { background: #3498db; color: white; }
		tr:nth-child(even) { background: #f8f9fa; }
	</style>
</head>
<body>

<div class="box">
	<h1>📥 Insert Transporteurs into Database</h1>
	
	<?php
	
	$transporteurs = array(
		array('id' => 1, 'name' => 'BOUTCHRAFINE'),
		array('id' => 2, 'name' => 'SOMATRIN'),
		array('id' => 3, 'name' => 'MARATRANS'),
		array('id' => 4, 'name' => 'G.T.C'),
		array('id' => 5, 'name' => 'DOUKALI'),
		array('id' => 6, 'name' => 'COTRAMAB'),
		array('id' => 7, 'name' => 'CORYAD'),
		array('id' => 8, 'name' => 'CONSMETA'),
		array('id' => 9, 'name' => 'CHOUROUK'),
		array('id' => 10, 'name' => 'CARRE'),
		array('id' => 11, 'name' => 'STB'),
		array('id' => 12, 'name' => 'FASTTRANS')
	);
	
	try {
		$db = Cnx();
		
		echo '<h2>Inserting Transporteurs...</h2>';
		
		$inserted = 0;
		$failed = 0;
		
		echo '<table>';
		echo '<tr><th>ID</th><th>Name</th><th>Status</th></tr>';
		
		foreach ($transporteurs as $t) {
			try {
				$stmt = $db->prepare("INSERT INTO transporteurs (id, name) VALUES (?, ?)");
				$result = $stmt->execute([$t['id'], $t['name']]);
				
				echo '<tr>';
				echo '<td>' . $t['id'] . '</td>';
				echo '<td>' . $t['name'] . '</td>';
				echo '<td class="success">✅ Inserted</td>';
				echo '</tr>';
				
				$inserted++;
				
			} catch (Exception $e) {
				echo '<tr>';
				echo '<td>' . $t['id'] . '</td>';
				echo '<td>' . $t['name'] . '</td>';
				echo '<td class="error">❌ Error: ' . $e->getMessage() . '</td>';
				echo '</tr>';
				
				$failed++;
			}
		}
		
		echo '</table>';
		
		echo '<h2>Summary:</h2>';
		echo '<p><span class="success">✅ Inserted: ' . $inserted . '</span></p>';
		if ($failed > 0) {
			echo '<p><span class="error">❌ Failed: ' . $failed . '</span></p>';
		}
		
		// Verify
		echo '<h2>Verification:</h2>';
		$result = $db->query("SELECT COUNT(*) as count FROM transporteurs");
		$row = $result->fetch(PDO::FETCH_ASSOC);
		echo '<p><strong>Total transporteurs in DB now:</strong> <span class="success">' . $row['count'] . '</span></p>';
		
		if ($row['count'] == 12) {
			echo '<p style="background: #d5f4e6; padding: 15px; border-radius: 5px; border-left: 5px solid #27ae60;">';
			echo '✅ Perfect! All 12 transporteurs are now in the database!<br>';
			echo 'You can now run the import script again!';
			echo '</p>';
		}
		
	} catch (Exception $e) {
		echo '<p class="error">❌ Fatal Error: ' . $e->getMessage() . '</p>';
	}
	
	?>
	
</div>

</body>
</html>