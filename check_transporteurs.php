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
		.box { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; border-left: 5px solid #3498db; }
		table { width: 100%; border-collapse: collapse; margin: 15px 0; }
		th, td { border: 1px solid #bdc3c7; padding: 12px; text-align: left; }
		th { background: #3498db; color: white; }
		.error { color: #e74c3c; }
		.success { color: #27ae60; }
	</style>
</head>
<body>

<div class="box">
	<h1>🔍 Check Transporteur IDs</h1>
	
	<?php
	
	try {
		$db = Cnx();
		
		echo '<h2>Transporteurs in Database:</h2>';
		
		$result = $db->query("SELECT * FROM transporteurs ORDER BY id");
		$transporteurs = $result->fetchAll(PDO::FETCH_ASSOC);
		
		if (empty($transporteurs)) {
			echo '<p class="error">❌ NO TRANSPORTEURS FOUND IN DATABASE!</p>';
		} else {
			echo '<table>';
			echo '<tr><th>ID</th><th>Name</th></tr>';
			
			foreach ($transporteurs as $t) {
				echo '<tr>';
				echo '<td><strong>' . $t['id'] . '</strong></td>';
				echo '<td>' . $t['name'] . '</td>';
				echo '</tr>';
			}
			
			echo '</table>';
			
			// Show the mapping
			echo '<h2>Current Mapping in Code:</h2>';
			$mapping = array(
				'BOUTCHRAFINE' => 1,
				'SOMATRIN' => 2,
				'MARATRANS' => 3,
				'G.T.C' => 4,
				'DOUKALI' => 5,
				'COTRAMAB' => 6,
				'CORYAD' => 7,
				'CONSMETA' => 8,
				'CHOUROUK' => 9,
				'CARRE' => 10,
				'STB' => 11,
				'FASTTRANS' => 12
			);
			
			echo '<table>';
			echo '<tr><th>Wialon Group</th><th>Expected ID</th><th>Exists?</th></tr>';
			
			$db_ids = array_column($transporteurs, 'id');
			
			foreach ($mapping as $group => $id) {
				$exists = in_array($id, $db_ids) ? '✅ YES' : '❌ NO';
				$color = in_array($id, $db_ids) ? 'success' : 'error';
				echo '<tr>';
				echo '<td>' . $group . '</td>';
				echo '<td>' . $id . '</td>';
				echo '<td class="' . $color . '">' . $exists . '</td>';
				echo '</tr>';
			}
			
			echo '</table>';
		}
		
	} catch (Exception $e) {
		echo '<p class="error">❌ Error: ' . $e->getMessage() . '</p>';
	}
	
	?>
	
</div>

</body>
</html>