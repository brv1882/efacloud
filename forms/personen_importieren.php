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

$tmp_upload_file = "";

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/personen_importieren";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    $mode = (intval($entered_data["Modus"]) > 0) ? intval($entered_data["Modus"]) : $_SESSION["personsImportMode"];
    $_SESSION["personsImportMode"] = $mode;
    
    include_once '../classes/efa_tables.php';
    $efa_tables = new Efa_tables($toolbox, $socket);
    include_once '../classes/efa_audit.php';
    $efa_audit = new Efa_audit($efa_tables, $toolbox);
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
                $_SESSION["io_table"] = "efa2persons";
                if (! file_exists("../log/io"))
                    mkdir("../log/io");
                file_put_contents("../log/io/" . $_SESSION["io_file"], $tmp_upload_file);
                $records = $toolbox->read_csv_array("../log/io/" . $_SESSION["io_file"]);
                
                $import_check_info = "";
                $import_check_errors = "";
                $r = 0;
                foreach ($records as $record) {
                    $r ++;
                    $import_check_prefix = "Prüfe Zeile " . $r . ": " . $record["FirstName"] . " " .
                             $record["LastName"];
                    $modification_result = $efa_audit->modify_version(6, $record, $mode, false, false);
                    if (strlen($modification_result) == 0)
                        $import_check_info .= $import_check_prefix . " - ok.<br>";
                    else
                        $import_check_errors .= $import_check_prefix . " - " . $modification_result . ".<br>";
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
            $r ++;
            $import_done_prefix = "Führe aus Zeile " . $r . ": " . $record["FirstName"] . " " .
                     $record["LastName"];
            $modification_result = $efa_audit->modify_version(6, $record, $mode, true, true);
            if (strlen($modification_result) == 0)
                $import_done_info .= $import_done_prefix . " - ok.<br>";
            else
                $import_done_info .= $import_done_prefix . " - " . $modification_result . ".<br>";
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
	<h3>Personen importieren</h3>
	<p>Hier können Daten der efa-Tabelle Personen als Massentransaktion
		modifiziert werden.</p>
<?php
if ($todo == 1) { // step 1. Texts for output
    ?>
	<p>Dateiformat und Feldnamen</p>
	<ol>
		<li>Die zu importierende csv-Datei muss als mit Trenner: ';' und
			Textmarker: '"' und in der ersten Zeile die technischen Feldnamen der
			Datenbanktabelle ausweisen (Liste der Namen vgl. Menüfunktion
			"efaCloud einstellen > Datenstruktur"). Alternativ sind die
			Extrafelder 'StatusName', 'ValidFromDate', 'InvalidFromDate' zulässig
			(s.u.).</li>
		<li>Groß-Klein-Schreibung ist bei den Namen relevant. Werden ungültige
			Feldnamen verwendet, wird der Import abgelehnt.</li>
	</ol>
	<p>Extrafelder für vereinfachten Import:</p>
	<ol>
		<li>StatusName: Die zulässigen Werte für StatusName sind die, die in
			efa2status als Namen angegeben sind, z. B. "Gast" (ohne die
			Anführungszeichen). Der Wert wird dann durch die Id ersetzt und in
			das Feld StatusId geschrieben.</li>
		<li>ValidFromDate, InvalidFromDate: anstelle des schwer lesbaren
			Unix-Zeitstempels kann hier ein Datum im Format TT.MM.JJJJ angegeben
			werden, das dann in einen Zeitstempel umgewandelt wird beim Import
			und in die Felder ValidFrom/InvalidFrom geschrieben wird.
	
	</ol>
	<p>Importoptionen:</p>
	<ol>
		<li><b>neu anlegen:</b> eine neue Person als Datensatz hinzufügen.<br>Das
			Datenfeld 'Id' darf nicht vorhanden sein, 'FirstName' und 'LastName'
			dürfen nicht leer und eine Person mit diesem Namen darf noch nicht
			vorhanden sein. 'Gender' muss MALE oder FEMALE sein, und 'StatusName'
			bzw. 'StatusId' einen gültigen Wert enthalten. Es wird empfohlen
			'ValidFrom' bzw. 'ValidFromDate' ebenfalls anzugeben, alternativ wird
			die aktuelle Uhrzeit verwendet.<br> <i>ein Beispiel:<br>
				FirstName;LastName;Gender;Birthday;StatusName;ValidFromDate;MembershipNo<br>
				Max;Kustermann;MALE;2005;Junior(in);01.10.2022;4711
		</i></li>
		<li><b>ändern:</b> den aktuell gültigen Datensatz einer Person ändern.
			<br> Die Datenfelder 'FirstName' und 'LastName' und/oder 'Id' müssen
			eine vorhandene und aktuell gültige Person eindeutig definieren. Wenn
			alle drei Felder angegeben sind, hat das Feld 'Id' Vorrang für die
			Auswahl. Die Felder 'ValidFrom' und 'ValidFromDate' müssen fehlen
			oder leer sein.<br> <i>ein Beispiel (ändert den Namen und den
				Status):<br> Id;FirstName;LastName;StatusName<br>
				12345678-1234-5678-90ab-1234567890ab;Max;Mustermann;Senior(in)</i></li>
	</ol>
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