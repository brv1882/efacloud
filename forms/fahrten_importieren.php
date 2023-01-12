<?php
/**
 * The form for upload and import of persons' records (not efaCloudUsers, but efa2persons).
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';
include_once '../classes/efa_tables.php';
include_once '../classes/efa_uuids.php';
$efa_uuids = new Efa_uuids($toolbox, $socket);

$tmp_upload_file = "";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/fahrten_importieren";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    include_once '../classes/efa_record.php';
    $efa_record = new Efa_record($toolbox, $socket);
    $valid_records = array();
    $user_id = $_SESSION["User"][$toolbox->users->user_id_field_name];
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        //
        // step 1 form was filled. Import verification
        //
        if (strlen($_FILES['userfile']["name"]) < 1) {
            // Special case upload error. Userfile can not be checked after
            // being entered, must be checked
            // after upload was tried.
            $form_errors .= "Keine Datei angegeben. bitte noch einmal versuchen.";
        } else {
            $tmp_upload_file = file_get_contents($_FILES['userfile']["tmp_name"]);
            if (! $tmp_upload_file)
                $form_errors .= "Unbekannter Fehler beim Hochladen. bitte noch einmal versuchen.";
            else {
                $_SESSION["io_file"] = $_FILES['userfile']["name"];
                $_SESSION["io_table"] = "efa2logbook";
                if (! file_exists("../log/io"))
                    mkdir("../log/io");
                file_put_contents("../log/io/" . $_SESSION["io_file"], $tmp_upload_file);
                $records = $toolbox->read_csv_array("../log/io/" . $_SESSION["io_file"]);
                $import_check_info = "";
                $import_check_errors = "";
                $r = 0;
                foreach ($records as $record) {
                    // check header once
                    if ($r == 0) {
                        $mismatching_names = "";
                        $field_names_table = $socket->get_column_names("efa2logbook");
                        foreach ($record as $key => $value)
                            if (! in_array($key, $field_names_table))
                                $mismatching_names .= $key . ",";
                        if (strlen($mismatching_names) > 0)
                            $import_check_errors .= "Folgende Datenfelder sind keine Felder der Fahrtenbücher-Tabelle: " .
                                     $mismatching_names . "<br>";
                    }
                    // check records.
                    $record_resolved = $efa_uuids->resolve_session_record($record);
                    $r ++;
                    $key_str = "Fahrtenbuch " . $record["Logbookname"] . ", Fahrt #" . $record["EntryId"];
                    $import_check_prefix = "Prüfe Zeile " . $r . ": " . $key_str;
                    $validation1_result = $efa_record->check_unique_and_not_empty($record_resolved, 
                            "efa2logbook", 1);
                    if (strlen($validation1_result) > 0)
                        $import_done_info .= $import_check_prefix . " - $validation1_result.<br>";
                    else {
                        $validation2_result = $efa_record->validate_record_APIv1v2("efa2logbook", 
                                $record_resolved, 1, $user_id);
                        if (is_array($validation2_result)) {
                            if ($validation2_result[1] === true)
                                $import_check_info .= $import_check_prefix .
                                         " - Die Fahrt gibt es bereits, sie wird nicht importiert.<br>";
                            else
                                $import_check_info .= $import_check_prefix . " - ok.<br>";
                        } else
                            $import_check_errors .= $import_check_prefix . " - " . $validation2_result .
                                     ".<br>";
                    }
                }
                
                // only move on, if import did not return an error.
                if (strlen($import_check_errors) == 0)
                    $todo = $done + 1;
                else
                    $form_errors .= $import_check_errors;
            }
        }
    } elseif ($done == 2) {
        //
        // step 2 import execution
        //
        $records = $toolbox->read_csv_array("../log/io/" . $_SESSION["io_file"]);
        $import_done_info = "";
        $r = 0;
        foreach ($records as $record) {
            $record_resolved = $efa_uuids->resolve_session_record($record);
            if (strlen($record_resolved["EndDate"]) == 0)
                unset($record_resolved["EndDate"]);
            $r ++;
            $key_str = $record["Logbookname"] . ": " . $record["EntryId"];
            $import_done_prefix = "Lade Zeile " . $r . ": " . $key_str;
            $validation1_result = $efa_record->check_unique_and_not_empty($record_resolved, "efa2logbook", 1);
            if (strlen($validation1_result) > 0)
                $import_done_info .= $import_done_prefix . " - $validation1_result.<br>";
            else {
                $validation2_result = $efa_record->validate_record_APIv1v2("efa2logbook", $record_resolved, 1, 
                        $user_id);
                if (! is_array($validation2_result)) {
                    $import_done_info .= $import_done_prefix . " - $validation2_result.<br>";
                } else {
                    if ($validation2_result[1] === true)
                        $import_done_info .= $import_done_prefix .
                                 " - Die Fahrt gibt es bereits, sie wird nicht importiert.<br>";
                    else {
                        $prepared_record = Efa_tables::register_modification($validation2_result[0], time(), 
                                1, "insert");
                        if (isset($prepared_record["EndDate"]) && (strlen($prepared_record["EndDate"]) < 5))
                            unset($prepared_record["EndDate"]);
                        $modification_result = $efa_record->modify_record("efa2logbook", $prepared_record, 1, 
                                $user_id, false);
                        if (strlen($modification_result) == 0)
                            $import_done_info .= $import_done_prefix . " - ok.<br>";
                        else
                            $import_done_info .= $import_done_prefix . " - " . $modification_result . ".<br>";
                    }
                }
            }
        }
        
        unlink("../log/io/" . $_SESSION["io_file"]);
        $todo = $done + 1;
    }
}

// ==== continue with the definition and eventually initialization of form to fill for the next step
if (isset($form_filled) && ($todo == $form_filled->get_index())) {
    // redo the 'done' form, if the $todo == $done, i. e. the validation failed.
    $form_to_fill = $form_filled;
} else {
    // if it is the start or all is fine, use a form for the coming step.
    $form_to_fill = new Tfyh_form($form_layout, $socket, $toolbox, $todo, $fs_id);
}

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Fahrten importieren</h3>
	<p>Hier können neue Fahrten in die efa-Tabelle Fahrtenbücher als
		Massentransaktion importiert werden.</p>
	<p>Das ist aufwändig. Rechne pro 5 Fahrten mit einer Sekunde
		Bearbeitungszeit zur Prüfung und für den Import.</p>
<?php
if ($todo == 1) { // step 1. Texts for output
    ?>
	<p>Dateiformat und Feldnamen</p>
	<ol>
		<li>Die zu importierende csv-Datei muss als mit Trenner: ';' und
			Textmarker: '"' und in der ersten Zeile die technischen Feldnamen der
			Datenbanktabelle ausweisen (Liste der Namen vgl. Menüfunktion
			"efaCloud einstellen > Datenstruktur").</li>
		<li>Groß-Klein-Schreibung ist bei den Namen relevant. Werden ungültige
			Feldnamen verwendet, wird der Import abgelehnt.</li>
		<li>Es dürfen keine UUIDs verwendet werden, importiert werden immer
			Namen, so wie ja auch immer Namen eingetragen werden.</li>
	</ol>
	<p>Zulässige Datenfelder sind:</p>
	<ul>
		<li>Logbookname - Zeichenkette. Muss vorhanden sein.</li>
		<li>EntryId - Ganzzahl. Muss vorhanden sein.</li>
		<li>Date - Zeichenkette (Format: YYYY-MM-DD)</li>
		<li>EndDate - Zeichenkette (Format: YYYY-MM-DD)</li>
		<li>BoatVariant - Ganzzahl</li>
		<li>BoatName - Zeichenkette</li>
		<li>CoxName - Zeichenkette</li>
		<li>Crew1Name ... Crew24Name - Zeichenkette</li>
		<li>StartTime - Zeichenkette</li>
		<li>EndTime - Zeichenkette</li>
		<li>DestinationName - Zeichenkette</li>
		<li>WatersNameList - Zeichenkette</li>
		<li>Distance - Zeichenkette</li>
		<li>SessionType - Zeichenkette(Auswahl aus dem Typensatz, englische
			Bezeichnungen, z.B. NORMAL, TRAININGSCAMP)</li>
	</ul>
	<p>Immer erst eine Prüfung vor Import</p>
	<ol>
		<li>die Tabelle hochladen. Es wird angezeigt, ob ein Import möglich
			ist und was dabei geschehen wird.</li>
		<li>Import bestätigen. Die Datensätze werden importiert.</li>
	</ol>
		<?php
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html(true); // enable file upload
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} elseif ($todo == 2) { // step 2. Texts for output
    ?>
	<p>Der Datei-Upload und die Daten-Prüfung war erfolgreich. Im Folgenden
		ist dargestellt, was importiert wird.</p>
		<?php
    // no form errors possible at this step. just a button clicked.
    echo $import_check_info;
    ?>
	<p>Im nächsten Schritt wird die Tabelle hochgeladen und so, wie
		dargestellt, importiert. Bitte bestätige, dass der Import durchgeführt
		werden soll (kein rückgängig möglich).</p>
		<?php
    // no form errors possible at this step. just a button clicked.
    echo $form_to_fill->get_html(false);
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} elseif ($todo == 3) { // step 3. Texts for output
    ?>
	<p>
		Der Datei-Import wurde durchgeführt. <br />Das Protokoll dazu ist:
	</p>
<?php
    echo "<p>" . $import_done_info . "</p>";
}

// Help texts and page footer for output.
?>
</div><?php
end_script();