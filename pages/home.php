<?php
/**
 * The start of the session after successfull login.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

$versions_string = file_get_contents(
        'https://efacloud.org/src/scanversions.php?own=' .
                 htmlspecialchars(file_get_contents("../public/version")));
$versions = explode("|", $versions_string);
rsort($versions);
$latest_version = $versions[0];
if (strpos($versions[0], "Versionswechsel") !== false)
    $latest_version = $versions[1];

$current_version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "";

if (strcasecmp($latest_version, $current_version) != 0)
    $version_notification = "<b>&nbsp;Hinweis:</b> Es gibt eine neuere Programmversion: " . $latest_version .
             ". <a href='../pages/ec_upgrade.php'>&nbsp;&nbsp;<b>==&gt; AKTUALISIEREN</a></b>.";
else
    $version_notification = "&nbsp;Ihr efaCloud Server ist auf dem neuesten Stand.";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

$verified_user = $socket->find_record_matched($toolbox->users->user_table_name, 
        [$toolbox->users->user_id_field_name => $_SESSION["User"][$toolbox->users->user_id_field_name]
        ]);
$short_home = (strcasecmp($_SESSION["User"]["Rolle"], "admin") != 0) &&
         (strcasecmp($_SESSION["User"]["Rolle"], "board") != 0);
?>

<!-- START OF content -->
<div class="w3-container">
	<h3><?php echo $verified_user["Vorname"] . " " . $verified_user["Nachname"]?></h3>

<?php
echo "<table>";
echo "<tr><td><b>Mitglied Nr.</b></td><td>" . $verified_user[$toolbox->users->user_id_field_name] .
         "</td></tr>\n";
echo "<tr><td><b>" . $verified_user["Vorname"] . " " . $verified_user["Nachname"] . "</b></td><td>" .
         $verified_user["EMail"] . "</td></tr>\n";
echo "<tr><td><b>Rolle</b></td><td>" . $verified_user["Rolle"] . "</td></tr>\n";
echo "</table>";
if (strcasecmp($verified_user["Rolle"], "bths") == 0)
    echo '<h3><a href="../pages/bths.php">Zum Fahrtenbuch...</a></h3>';
if (! $short_home)
    echo "<p>" . $version_notification . "</p>";

?>
	<h4>Boote unterwegs</h4>
	<iframe src="../public/info.php?type=onthewater&mode=7"
		title="Boote auf dem Wasser" style="width: 100%; border: none"></iframe>
<?php
if (! $short_home) {
    include_once "../classes/efa_config.php";
    $efa_config = new Efa_config($toolbox);
    echo "<h4>Aktive Clients</h4><p>" . $efa_config->get_last_accesses_API($socket, true, true) . "</p>";
    ?>
</div>
<?php
} else
    echo "<p>Um Deine Fahrten einzusehen, nutze bitte efaWeb, Umstieg siehe Menü links.</p>";
end_script();