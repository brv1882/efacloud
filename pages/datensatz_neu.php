<?php
/**
 * The page select a table for new records.
 */
// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
include_once "../classes/efa_tables.php";

// === PAGE OUTPUT ===================================================================

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

// page heading, identical for all workflow steps
?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Einen neuen Datensatz anlegen</h3>
	<p>Hier kannst Du für viele efa-Tabellen einen neuen Datensatz anlegen.
		Für einen neuen efaCloud Nutzer verwende bitte das efaCloud Nutzer
		Menü.</p>
</div>
<div class="w3-container">
	<table>
		<tr>
			<th>Tabelle</th>
			<th>Link zum Formular</th>
		</tr>
<?php
$forbidden = ["efa2autoincrement" => "Weitere Systemzähler können nicht ergänzt werden.",
        "efa2boats" => false,"efa2boatdamages" => "Für neue Schadensmeldungen nutze bitte efaWeb.",
        "efa2boatreservations" => "Für neue Reservierungen nutze bitte efaWeb.",
        "efa2boatstatus" => "Ein neuer Datensatz für einen Bootsstatus wird generiert, " .
                 "wenn ein neuer Datensatz für ein Boot generiert wird. " .
                 "Den Bootsstatus gibt es nicht unabhängig vom Boot.","efa2clubwork" => false,
                "efa2crews" => false,"efa2destinations" => false,
                "efa2fahrtenabzeichen" => "Zur Ergänzung der Fahrtenabzeichen muss efa als PC-Programm genutzt werden.",
                "efa2groups" => false,"efa2logbook" => "Für neue Fahrten nutze bitte efaWeb.",
                "efa2messages" => "Für neue Nachrichten nutze bitte efaWeb.","efa2persons" => false,
                "efa2sessiongroups" => false,
                "efa2statistics" => "Zur Ergänzung von Statistiken muss efa als PC-Programm genutzt werden.",
                "efa2status" => false,"efa2waters" => false

];

foreach ($forbidden as $tablename => $forbidden) {
    $local_name = Efa_tables::locale_names()[$tablename];
    echo "<tr><td>$local_name</td>";
    if ($forbidden)
        echo "<td>$forbidden</td></tr>";
    else
        echo "<td><a href='../forms/datensatz_aendern.php?table=" . $tablename .
                 "&ecrid=new'>Neuer Eintrag</a></div>";
}

?>
</table>
</div>

<?php
end_script();

    