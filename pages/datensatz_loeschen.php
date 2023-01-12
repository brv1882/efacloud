<?php

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$record_to_delete = false;
if (isset($_GET["table"]) && isset($_GET["ID"])) {
    $id_name = "ID";
    $id_value = $_GET["ID"];
} elseif (isset($_GET["table"]) && isset($_GET["ecrid"])) {
    $id_name = "ecrid";
    $id_value = $_GET["ecrid"];
} else 
    $toolbox->display_error("Nicht zulässig.",
            "Die Seite '" . $user_requested_file .
            "' muss als Folgeseite mit einer Datensatzidentifikation aufgerufen werden.", __FILE__);
    
$record_to_delete = $socket->find_record_matched($_GET["table"], [$id_name => $id_value
]);
if ($record_to_delete !== false) {
    include_once "../classes/efa_record.php";
    $efa_record = new Efa_record($toolbox, $socket);
    $delete_result = $efa_record->modify_record($_GET["table"], $record_to_delete, 3, 
            $_SESSION["User"][$toolbox->users->user_id_field_name], false);
} else
    $delete_result = "Datensatz nicht vorhanden.";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Datensatz löschen</h3>
	<?php
if (! is_numeric($delete_result))
    echo "<p>Der Datensatz mit der $id_name '$id_value' aus der Tabelle " . $_GET["table"] .
             " konnte nicht gelöscht werden. Die Datenbank meldet: " . $delete_result;
else {
    if (intval($delete_result) == 1)
        echo "<p>Der Datensatz mit der $id_name '$id_value' aus der Tabelle " . $_GET["table"] .
                 " ist bereits ein Löscheintrag.";
    else
        echo "<p>Der Datensatz mit der $id_name '$id_value' aus der Tabelle " . $_GET["table"] .
                 " wurde gelöscht.";
}
echo "</p>";
if (intval($delete_result) == 2)
    echo "<p>Ein Papierkorbeintrag konnte leider nicht erstellt werden.</p>";
?>
	<p>
		<a href='../pages/show_changes.php'>&gt;&gt;&gt; Änderungslog ansehen</a>
	</p>
</div>

<?php
end_script(true);