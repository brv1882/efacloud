<?php
/**
 * A page to audit the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/efa_tables.php";
include_once '../classes/efa_db_layout.php';
include_once "../classes/efa_tools.php";
$efa_tools = new Efa_tools($toolbox, $socket);

// ===== Improve data base status, if requested
$improve = (isset($_GET["do_improve"])) ? $_GET["do_improve"] : "";
$do_improve = (strcmp($improve, "now") == 0);
$improvements = "";

// maximum number of records which will be added an ecrid, if missing, in one go. Should never be hit.
$max_add_ecrids = 1000;
if ($do_improve) {
    $upgrade_success = $efa_tools->upgrade_efa_tables(true);
    $improvements = ($upgrade_success) ? "<b>Fertig</b><br>Das Tabellenlayout wurde angepasst. " : "<b>Fehler</b><br>Das Tabellenlayout konnte nicht angepasst werden. Details siehe '../log/efa_tools.log'. ";
    $added_ecrids = $efa_tools->add_ecrids($max_add_ecrids);
    $improvements .= (($added_ecrids > 0) ? $added_ecrids .
             " ecrids wurden hinzugefügt. (Der Schritt aktualisiert maximal " . $max_add_ecrids .
             " Datensätze und muss ggf. wiederholt werden.)<br>" : ".");
    $improvements .= "<br>";
    if ($upgrade_success) {
        $cfg_db = $toolbox->config->get_cfg_db();
        $cfg_db["db_layout_version"] = Efa_db_layout::$db_layout_version_target;
        $cfg_db["db_up"] = Tfyh_toolbox::swap_lchars($cfg_db["db_up"]);
        $cfgStr = serialize($cfg_db);
        $cfgStrBase64 = base64_encode($cfgStr);
        $byte_cnt = file_put_contents("../config/settings_db", $cfgStrBase64);
        $improvements .= "Die Datenbank-Konfiguration wurde aktualisiert ($byte_cnt Bytes).<br>";
    }
    $improvements .= '<br>';
}
$optimization_needed = false;

// ===== Configuration check
$db_layout_config = "<b>Ergebnis der Konfigurationsprüfung</b><ul>";
// compare the current version. $efa_tools still remembers the version before improvement
$layout_cfg_is_target = (intval(Efa_db_layout::$db_layout_version_target) ==
         intval($efa_tools->db_layout_version));
if ($layout_cfg_is_target) {
    $db_layout_config .= "<li>OK. " . "Konfigurationsparameter Layout Version " .
             Efa_db_layout::$db_layout_version_target;
} else {
    $optimization_needed = true;
    $db_layout_config .= "<li>NICHT OK.</li><li>";
    $db_layout_config .= "In der Konfiguration hinterlegt: " . $efa_tools->db_layout_version . "</li><li>";
    $db_layout_config .= "Standard für diese Programmversion: " . Efa_db_layout::$db_layout_version_target;
}
$db_layout_config .= "</li></ul>";

// ===== Size check
// start with the data base size in kB
// ===================================
$audit_result = "<li><b>Liste der Tabellen nach Größe</b></li>\n<ul>";
$table_sizes = $socket->get_table_sizes_kB();
$total_size = 0;
$table_record_count_list = "<b>Größenprüfung: Tabellen und Datensätze</b><ul>";
$total_record_count = 0;
$total_table_count = 0;
foreach ($table_sizes as $table_name => $table_size) {
    $record_count = $socket->count_records($table_name);
    $total_record_count += $record_count;
    $total_size += intval($table_size);
    $total_table_count ++;
    $table_record_count_list .= "<li>$table_name: $record_count Datensätze, $table_size kB]</li>";
}
$table_record_count_list .= "<li>in Summe: $total_record_count Datensätze, $total_table_count Tabellen, $total_size kB.</li></ul>";

// ===== Layout implementation check
$efa_tools->change_log_path("../log/sys_db_audit.log");
$verification_result = "<b>Ergebis der Layoutprüfung</b><ul><li>";
$db_layout_verified = $efa_tools->update_database_layout(
        $_SESSION["User"][$toolbox->users->user_id_field_name], Efa_db_layout::$db_layout_version_target, true);
if ($db_layout_verified) {
    $verification_result .= "OK. Das Layout stimmt mit dem Standard der Programmversion = Version " .
             Efa_db_layout::$db_layout_version_target . " überein.";
} else {
    $optimization_needed = true;
    $verification_result .= "NICHT OK.</li><li>" . str_replace("\n", "</li><li>", 
            str_replace("Verification failed", "<b>Verification failed</b>", 
                    file_get_contents("../log/sys_db_audit.log")));
}
$efa_tools->change_log_path("");
$verification_result .= "</li></ul>";

// ===== Ecrid filling check
$total_no_ecrids_count = 0;
$no_ecrid_record_count_list = "<b>Datensätze ohne ecrid" .
         "<sup class='eventitem' id='showhelptext_UUIDecrid'>&#9432;</sup>" . " Identifizierung</b><ul>";
foreach ($table_sizes as $tn => $table_size) {
    if (isset($efa_tools->ecrid_at[$tn]) && ($efa_tools->ecrid_at[$tn] == true)) {
        $records_wo_ecrid = $socket->find_records_sorted_matched($tn, ["ecrid" => ""
        ], $max_add_ecrids, "NULL", "", true);
        $no_ecrids_count = ($records_wo_ecrid === false) ? 0 : count($records_wo_ecrid);
        $colnames = $socket->get_column_names($tn);
        if (! in_array("ecrid", $colnames)) {
            $no_ecrids_count = $socket->count_records($tn);
        }
        $total_no_ecrids_count += $no_ecrids_count;
        if ($no_ecrids_count > 0)
            $no_ecrid_record_count_list .= "<li>" . $tn . " [" .
                     (($no_ecrids_count == $max_add_ecrids) ? strval($max_add_ecrids) . "+" : $no_ecrids_count) .
                     "]</li>";
    }
}
if ($total_no_ecrids_count > 0) {
    $optimization_needed = true;
    $no_ecrid_record_count_list .= "<li>NICHT OK</li></ul>";
} else
    $no_ecrid_record_count_list .= "<li>OK. Alle Datensätze enthalten die erforderliche ecrid-Identifizierung.</li></ul>";

// ===== data integrity auditing
include_once "../classes/efa_audit.php";
$efa_audit = new Efa_audit($toolbox, $socket);
$period_integrity_result = $efa_audit->period_correctness_audit();
$period_integrity_result = "<b>Ergebnis der Periodenkonformitätsprüfung</b><ul>" . $period_integrity_result .
         "</ul>";
$data_integrity_result = $efa_audit->data_integrity_audit(true);
$data_integrity_result_list = "<b>Ergebnis der Datenintegritätsprüfung</b><ul>" . $data_integrity_result .
         "</ul>";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<!-- START OF content -->
<div class="w3-container">
	<h3>Audit für die Datenbank <?php echo $socket->get_db_name(); ?><sup
			class='eventitem' id='showhelptext_Audit'>&#9432;</sup>
	</h3>
	<p>Hier das Ergebnis der Prüfung der Datenbank</p>
	<?php
echo $improvements;
echo $db_layout_config;
echo $verification_result;
echo $no_ecrid_record_count_list;
if ($optimization_needed)
    echo '<p><a href="?do_improve=now"><span class="formbutton">Jetzt korrigieren - Warten - dauert bis zu 5 Minuten!</span></a><br /><br /></p>';
echo $table_record_count_list;
echo $period_integrity_result;
echo $data_integrity_result_list;

?>
</div>
<?php
end_script();