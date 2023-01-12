<?php
/**
 * Page display file. Shows all mails available to public from ARB Verteler.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$ecrid = (isset($_GET["ecrid"])) ? $_GET["ecrid"] : false;
$tablename = (isset($_GET["table"])) ? $_GET["table"] : false;
$restore = (isset($_GET["restore_version"])) ? intval($_GET["restore_version"]) : 0;
$record = $socket->find_record($tablename, "ecrid", $ecrid);
$modify_result = "";

// restore a version, if requested.
if ($restore > 0) {
    // cache the history
    $history = $record["ecrhis"];
    // clear the record and build anew
    include_once "../classes/efa_record.php";
    $record = Efa_record::clear_record_for_delete($tablename, $record);
    // first restore the history
    $record["ecrhis"] = $history;
    // now rebuild the record
    $versions = $socket->get_history_array($history);
    $record_version = [];
    foreach ($versions as $version)
        if ($version["version"] <= $restore)
            $record_version = array_merge($record_version, $version["record_version"]);
    if (isset($record_version["ecrid"]) && (strcasecmp($record_version["ecrid"], $record["ecrid"]) == 0)) {
        include_once "../classes/efa_tables.php";
        $record_version = Efa_tables::register_modification($record_version, time(), 
                $record_version["ChangeCount"], "update");
        include_once "../classes/efa_record.php";
        $efa_record = new Efa_record($toolbox, $socket);
        $modify_result = $efa_record->modify_record($tablename, $record_version, 2, 
                $_SESSION["User"][$toolbox->users->user_id_field_name], false);
        $record = $socket->find_record($tablename, "ecrid", $ecrid);
    }
}

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h2>Versionsverlauf eines Datensatzes</h2>
	<p>Die Versionen sind neueste zuerst aufgeführt, jeweils nur die in der
		Version gegenüber der Vorversion veränderten Datenfelder. Verwendung
		gestattet nur zum geregelten Zweck.</p>
	<h4>Aus der Tabelle '<?php echo $tablename ?>'</h4>
<?php
if (strlen($modify_result) > 0)
    echo "<h5>Die Version V" . $restore .
             " des Datensatz in der Tabelle '$tablename' mit der Datensatz-ID '$ecrid' konnte nicht wiederhergestellt werden. Grund: " .
             $modify_result . "<h5>";
if ($record === false)
    echo "Der Datensatz in der Tabelle '$tablename' mit der Datensatz-ID '$ecrid' konnte nicht gefunden werden.";
if (isset($record["ecrhis"]))
    echo $socket->get_history_html($record["ecrhis"], 
            "../pages/show_history.php?table=" . $tablename . "&ecrid=" . $ecrid);
else
    echo "Leider ist für diesen Datensatz keine Historie vorhanden.";
?>
	<!-- END OF Content -->
</div>

<?php
end_script();
