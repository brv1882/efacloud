<?php
/**
 * Generic record display file.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$id = (isset($_GET["id"])) ? intval($_GET["id"]) : 0; // identify user via ID
if ($id == 0)
    $toolbox->display_error("Nicht zulässig.", 
            "Die Seite '" . $user_requested_file .
                     "' muss mit der Angabe der id des anzuzeigenden Datensatzes aufgerufen werden.", 
                    $user_requested_file);
else
    $record_to_show = $socket->find_record_matched("efaCloudArchived", ["ID" => $id
    ]);
$tablename = $record_to_show["Table"];

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datensatzanzeige für einen archivierten Datensatz</h3>
	<p>Tabelle: <?php echo $tablename; ?><br>
	archviert zu: <?php echo $record_to_show["Time"]; ?><br>
	archivID: <?php echo $record_to_show["ID"]; ?></p>
</div>
<div class="w3-container">
	<table>
		<tr>
			<th>Datenfeld</th>
			<th>Wert</th>
		</tr>
	<?php
$toDisplay = json_decode($record_to_show["Record"]);
foreach ($toDisplay as $key => $value) {
            echo "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>\n";
}
?>
	</table>
</div>
<?php

end_script();
