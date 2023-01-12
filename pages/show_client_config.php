<?php
/**
 * Page display file. Shows all logs of the application.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$cfg = $toolbox->config->get_cfg();
$reference_client_id = $cfg["reference_client"];
if (strlen($reference_client_id) == 0)
    $reference_client_id = "[kein Referenz-Client definiert, bitte Einstellungen prüfen.]";

include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
$efa_config->parse_client_config();
$efa_config->load_efa_config();
$type_issues = str_replace("\n", "<br>", $efa_config->compare_client_types());
if (strlen($type_issues) == 0)
    $type_issues = "Keine Unterschiede in der Typendefinition.";
else
    $type_issues .= "Es wird dringend empfohlen, bei allen Clients die gleichen Typ-Definitionen zu verwenden." .
             "Denn über efaCloud tauschen sie Daten aus, die bei unerschiedlicher Konfiguration " .
             "kompromittert werden können. Die Korrektur muss in den efa-Programmen erfolgen.";
$config_issues = str_replace("\n", "<br>", $efa_config->compare_client_configs());
if (strlen($config_issues) == 0)
    $config_issues = "Keine Unterschiede in der relevanten Programmeinstellung.";
else
    $config_issues .= "Es wird dringend empfohlen, bei allen Clients die gleichen Konfigurationen zu verwenden." .
             "Denn über efaCloud tauschen sie Daten aus, die bei unerschiedlicher Konfiguration " .
             "kompromittert werden können. Die Korrektur muss in den efa-Programmen erfolgen.";
$logbooks_html = $efa_config->display_array($efa_config->logbooks);
$clubworkbooks_html = $efa_config->display_array($efa_config->clubworkbooks);
$project_cfg_html = $efa_config->display_array($efa_config->project);
$types_cfg_html = $efa_config->display_array($efa_config->types);
$config_cfg_html = $efa_config->display_array($efa_config->config);

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>
		Efa-client-Konfiguration<sup class='eventitem'
			id='showhelptext_EfaKonfiguration'>&#9432;</sup> anzeigen
	</h3>
	<p>
		efaCLoud liest alle Konfigurationen der angeschlossenen PCs und bildet
		daraus die Gesamtmenge der gültigen Fahrtenbücher und
		Vereinsarbeitsbücher. Für den Rest verwendet es die Konfiguration des
		Referenzclients<sup class='eventitem'
			id='showhelptext_Stammdaten'>&#9432;</sup>, d. h. des efa-Clients mit der <b>efaCloudUserID
		"<?php echo $reference_client_id; ?>"</b>. Das Ergebnis,
		einschließlich einer Konsistenzprüfung, ist hier zu sehen. Welcher
		efa-Client als Referenz verwendet wird, kann <b>in den <a
			href='../forms/configparameter_aendern.php'>Einstellungen</a>
		</b> angegeben werden.
	</p>
	<h4>Einstellungen für efaCloud</h4>
	<ul>
		<li>Aktuelles Fahrtenbuch: <?php echo $efa_config->current_logbook; ?></li>
		<li>Zeitraum: <?php echo date("d.m.Y", $efa_config->logbook_start_time) . " - " . date("d.m.Y", $efa_config->logbook_end_time); ?></li>
		<li>Beginn des Sportjahres: <?php echo $efa_config->sports_year_start; ?></li>
	</ul>
	<h4>Unterschiede bei der Konfiguration der verschiedenen Clients</h4>
	<?php echo "<p>" . $type_issues . "</p>"; ?>
	<?php echo "<p>" . $config_issues . "</p>"; ?>
	<h4>Fahrtenbücher aller Clients</h4>
	<?php echo $logbooks_html; ?>
	<h4>Vereinsarbeitsbücher aller Clients</h4>
	<?php echo $clubworkbooks_html; ?>
	<h4>Verwendete Projekteinstellungen</h4>
	<p>
		efaCloud verwendet die Einstellungen des Referenz-Clients, wenn er <b><a
			href='../forms/configparameter_aendern.php'>in den Einstellungen</a></b>
		festgelegt wurde. Wenn dort keine efaCloudUserID angegeben ist, werden
		die Projekteinstellungen des Demo-Vereins RC Weiss-Blau verwendet.
	</p>
	<?php echo $project_cfg_html; ?>
	<h4>Typ-Definitionen</h4>
	<?php echo $types_cfg_html; ?>
	<h4>Efa-Client Programmeinstellungen</h4>
	<?php echo $config_cfg_html; ?>
	<!-- END OF Content -->
</div>

<?php
end_script();
