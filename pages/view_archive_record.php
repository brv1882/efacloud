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
    $trash_record = $socket->find_record_matched("efaCloudArchived", ["ID" => $id
    ]);
$tablename = $trash_record["Table"];
include_once "../classes/efa_tables.php";
include_once "../classes/efa_archive.php";
$efa_archive = new Efa_archive($toolbox, $socket, $_SESSION["User"][$toolbox->users->user_id_field_name]);
$archive_records = $efa_archive->get_all_archived_versions($trash_record);
$archived_record = $efa_archive->decode_record($trash_record);
$records_timestamp_list = "";
if ($archive_records === false) {
    // non versionized record, just show the single timestamp
    $archived_for_time = $efa_archive->time_of_non_versionized_record($tablename, $archived_record);
    $archived_for_date = date("d.m.Y", $archived_for_time);
    $age_in_days = intval((time() - $archived_for_time) / 86400);
    $records_timestamp_list .= "Stichtag für Archivierung: $archived_for_date<br>\n" .
             "Alter in Tagen: $age_in_days<br>";
} else {
    // versionized record, show all versions timestamps
    $v = 1;
    $youngest = 0;
    foreach ($archive_records as $invalidFrom32 => $archive_record_version) {
        if ($invalidFrom32 > $youngest)
            $youngest = $invalidFrom32;
        $invalidFrom = date("d.m.Y", $invalidFrom32);
        $archive_id = $archive_record_version["ID"];
        $age_in_days = intval((time() - $invalidFrom32) / 86400);
        $records_timestamp_list .= $v . ". Version gültig bis: $invalidFrom, " .
                 "Alter in Tagen: $age_in_days (ID: <a href='../pages/view_archive_record.php?id=" .
                 $archive_id . "'>" . $archive_id . "</a>)<br>";
        $v ++;
    }
    $age_in_days = intval((time() - $youngest) / 86400);
    $records_timestamp_list = "Objekt gültig bis: " . date("d.m.Y", $youngest) . ", " .
             "Alter in Tagen: $age_in_days<br>" . $records_timestamp_list;
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>
		Datensatzanzeige für einen archivierten Datensatz<sup
			class='eventitem' id='showhelptext_Archiv'>&#9432;</sup>
	</h3>
	<p>archiviert aus der Tabelle: <?php echo $tablename; ?><br>
	Zeitpunkt der Archivierung: <?php echo date("d.m.Y H:i:s", strtotime($trash_record["Time"])); ?><br>
	Referenz (archiveID): <?php echo $trash_record["ID"]; ?><br>
	<?php echo $records_timestamp_list; ?></p>
</div>
<div class="w3-container">
	<table>
		<tr>
			<th>Datenfeld</th>
			<th>Wert</th>
		</tr>
	<?php
foreach ($archived_record as $key => $value) {
    if (in_array($key,Efa_tables::$date_fields[$tablename]) && (strlen($value) > 0)) 
        $value = date("d.m.Y", strtotime($value));
    echo "<tr><td>" . $key . "</td><td>" . $value . "</td></tr>\n";
}
?>
	</table>
</div>
<?php

end_script();
