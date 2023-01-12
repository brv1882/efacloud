<?php
/**
 * The form for upload and import of multiple data records as csv-tables. Based on the Tfyh_form class, please
 * read instructions their to better understand this PHP-code part.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once '../classes/tfyh_form.php';
include_once '../classes/efa_tables.php';
include_once '../classes/efa_archive.php';

// === APPLICATION LOGIC ==============================================================
// if validation fails, the same form will be displayed anew with error messgaes
$todo = ($done == 0) ? 1 : $done;
$form_errors = "";
$form_layout = "../config/layouts/archiv_wiederherstellen";

// ======== Start with form filled in last step: check of the entered values.
if ($done > 0) {
    $form_filled = new Tfyh_form($form_layout, $socket, $toolbox, $done, $fs_id);
    $form_filled->read_entered();
    $form_errors = $form_filled->check_validity();
    $entered_data = $form_filled->get_entered();
    
    // application logic, step by step
    if (strlen($form_errors) > 0) {
        // do nothing. This avoids any change, if form errors occured.
    } elseif ($done == 1) {
        $archived_less_than_days_ago = intval($entered_data["ArchivedLessThanDaysAgo"]);
        if ($archived_less_than_days_ago == 0)
            $archived_less_than_days_ago = Efa_tables::$forever_days;
        $tablename = $entered_data["Tabelle"];
        $efa_archive = new Efa_archive($toolbox, $socket,
                $_SESSION["User"][$toolbox->users->user_id_field_name]);
        $restore_result = $efa_archive->restore_form_archive($tablename, $archived_less_than_days_ago);
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
	<h3>Archivdaten<sup class='eventitem' id='showhelptext_Archiv'>&#9432;</sup> wiederherstellen</h3>
	<p>Hier können Datensätze einer Tabelle aus dem Archiv
		wiederhergestellt werden.</p>
<?php
if ($todo == 1) { // step 1. Texts for output
    ?>
	<p>Die Zulässigkeit der Wiederherstellung der Datensätze ist im
		Allgemeinen datenschutzrechtlich zu klären, da die Datensätze
		automatisch aufgrund der Datenschutzkonfiguration verschoben wurden.</p>
	<p>Bitte denke daran, auch die Einstellung zur Archivierung anzupassen,
		sonst werden die Datensätze beim nächsten Nachtlauf unter Umständen wieder archiviert.</p>
		<?php
    echo $toolbox->form_errors_to_html($form_errors);
    echo $form_to_fill->get_html();
    echo '<h5><br />Ausfüllhilfen</h5><ul>';
    echo $form_to_fill->get_help_html();
    echo "</ul>";
} elseif ($todo == 2) { // step 2. Texts for output
    ?>
	<p>Die Weiderherstellung ist abgeschlossen. Das Ergebnis: <?php echo $restore_result; ?>
		<br /> <a href="../forms/archiv_wiederherstellen.php">Weitere Daten
			wiederherstellen</a>.
	</p>
<?php
}
?>
</div><?php
end_script();