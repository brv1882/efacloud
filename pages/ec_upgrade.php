<?php
/**
 * The specific efaCloud upgrade page. Different from standard, as is supports version selection.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
// $debug_log = 'https://efacloud.org/support/debug/gather.php?data=';
$user_requested_file = __FILE__;
include_once "../classes/init.php";
$src_subdir = (isset($_GET["subdir"])) ? $_GET["subdir"] . "/" : "";

// Source Code path.
// ====== Depends on the ../config/settings_tfyh file.
$app_src_path = $toolbox->config->settings_tfyh["upgrade"]["src_path"];
if (is_null($$app_src_path))
    $app_src_path = $app_root . "/_src/server.zip";
$app_version_path = $toolbox->config->settings_tfyh["upgrade"]["version_path"];
if (is_null($app_version_path))
    $app_version_path = $app_root . "/_src/version";
$app_remove_files = $toolbox->config->settings_tfyh["upgrade"]["remove_files"];
if (is_null($app_remove_files))
    $app_remove_files = [];
$current_version = (file_exists("../public/version")) ? file_get_contents("../public/version") : "undefined";
$current_version_installed = (file_exists("../public/version")) ? filemtime("../public/version") : 0;
// -- The standard gathers the one and only $version_server, but efaCloud allows for selcting a version.
// $version_server = (isset($app_version_path) && (strlen($app_version_path) > 0)) ?
// file_get_contents($app_version_path) : "undefined";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<?php
if (! isset($_GET["upgrade"])) {
    $versions_request = "https://efacloud.org/src/" . $src_subdir . "scanversions.php?own=" .
             htmlspecialchars(file_get_contents("../public/version"));
    $versions_string = file_get_contents($versions_request);
    $versions = explode("|", $versions_string);
    ?>
<h3>Upgrade der efaCloud-Server-Anwendung</h3>
<p>Das Upgrade entpackt den Code und überschreibt dabei die vorhandenen
	Code-Dateien. Alle Bestandsdaten, wie zum Beispiel logs, uploads,
	backups usw. bleiben erhalten. Die Datenbank wird nicht modifiziert.</p>
<?php
    echo "<p>Aktuell ist installiert: <b>" . $current_version . "</b><br>Installationszeitpunkt war: <b>" .
             date("d.m.Y H:i:s", $current_version_installed) . "</b></p>";
    ?>
<p>Ein Upgrade kann nicht rückgängig gemacht werden. Es besteht
	allerdings die Möglichkeit, auf demselben Weg ein Downgrade auf alle
	noch verfügbaren Versionen durchzuführen.</p>

<p>
<form action='?upgrade=1' method='post'>
    <?php
    $release_notes = "";
    $version_options = "";
    foreach ($versions as $version) {
        if (strlen($version) > 1) {
            $release_notes = "<a href='https://efacloud.org/src/" . $src_subdir . $version .
                     "/release_notes.html' target='_blank'>Release Notes " . $version .
                     " nachlesen</a><br />\n" . $release_notes;
            $version_options = "<option value='" . $version . "'>" . $version . "</option>\n" .
                     $version_options;
        }
    }
    echo $release_notes;
    ?>
    <br /> <label><b>Auf folgende Version aktualisieren:&nbsp;</b></label><select
		name='version' class='formselector' style='padding-right: 15px'><?php
    echo $version_options;
    ?>
    </select> <br /> <br /> <label class="cb-container">Ich bin einverstanden, dass meine
			Server URL (<?php echo $app_root; ?>) und die nun installierte Version an efacloud.org
			übermittelt werden<input type="checkbox" name="agreeAutoregistration"
		checked><span class="cb-checkmark"></span>
	</label><br /> <input type='hidden' name='src_subdir'
		value='<?php echo $src_subdir; ?>' /> <input type='submit'
		class='formbutton' value='Jetzt aktualisieren' />
</form>
<p>Bitte beachten Sie: der Vorgang startet mit dem Klick auf den Knopf
	sofort und dauert nur wenige Sekunden.</p>


<?php
} else {
    
    $version_to_install = $_POST["version"];
    $agreeAutoregistration = isset($_POST["agreeAutoregistration"]) &&
             (strcasecmp($_POST["agreeAutoregistration"], "on") == 0);
    $src_subdir = $_POST["src_subdir"];
    
    if ($agreeAutoregistration) {
        // see https://stackoverflow.com/questions/5647461/how-do-i-send-a-post-request-with-php
        $url = 'https://www.efacloud.org/registration.php';
        $data = array('version' => $version_to_install,'server' => $app_root
        );
        // use key 'http' even if you send the request to https://...
        $options = array(
                'http' => array('header' => "Content-type: application/x-www-form-urlencoded\r\n",
                        'method' => 'POST','content' => http_build_query($data)
                )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        if ($result === false)
            echo "Übermittlung der Registrierung ist leider gescheitert.";
        else
            echo $result;
    }
    
    // Source Code path.
    // ==============================================================================================
    $efacloud_src_path = "https://efacloud.org/src/" . $src_subdir . $version_to_install . "/efacloud_server.zip";
    // ==============================================================================================
    // check loaded modules
    // ==============================================================================================
    $ref_config = ["bz2","calendar","Core","ctype","curl","date","dom","exif","fileinfo","filter","ftp",
            "gd","gettext","hash","iconv","json","libxml","mbstring","mysqli","openssl","pcre","pdo_mysql",
            "PDO","Phar","posix","Reflection","session","SimpleXML","SPL","standard","tokenizer",
            "xml","xmlreader","xmlwriter","xsl","zip","zlib"
    ];
    $this_config = get_loaded_extensions();
    $missing = [];
    foreach ($ref_config as $rcfg) {
        $contained = false;
        foreach ($this_config as $tcfg) {
            $contained = $contained || (strcmp($tcfg, $rcfg) == 0);
        }
        if (! $contained)
            $missing[] = $rcfg;
    }
    echo "<p >Installierte PHP-Module wurden geprüft.<br>";
    if (count($missing) > 0) {
        echo "Die folgenden Module fehlen auf dem Server im Vergleich zur Referenzinstallation:<br>";
        foreach ($missing as $m)
            echo "'" . $m . "', ";
        echo "Es ist möglich, dass efaCloud auch ohne diese Module läuft, wurde aber nicht getestet.<br><br>";
    } else
        "Alle Module der Referenzinstallation sind vorhanden.<br><br>";
    
    // fetch program source
    // ==============================================================================================
    echo "Lade den Quellcode von: " . $efacloud_src_path . " ...<br>";
    file_put_contents("src.zip", file_get_contents($efacloud_src_path));
    echo " ... abgeschlossen. Dateigröße: " . filesize("src.zip") . ".<br><br>";
    if (filesize("src.zip") < 1000) {
        echo "</p><p>Die Größe des Quellcode-Archivs ist zu klein. Da hat " .
                 "etwas mit dem Download nicht geklappt. Deswegen bricht der Prozess hier ab.</p></body></html>";
        exit();
    }
    
    // read settings, will be used as cache
    echo "Sichere die vorhandene Konfiguration ...<br>";
    $settings_db = file_get_contents("../config/settings_db");
    $settings_app = file_get_contents("../config/settings_app");
    $settings_colors = file_get_contents("../resources/app-colors.txt");
    // auth_provider API for external authentication
    $auth_provider_php = file_get_contents("../authentication/auth_provider.php");
    
    // Unpack source files
    // ==============================================================================================
    echo "Entpacke und kopiere das Quellcode-Archiv ...<br>";
    $zip = new ZipArchive();
    $res = $zip->open('src.zip');
    if ($res === TRUE) {
        $zip->extractTo('..');
        $zip->close();
        echo "Aktualisiere Versionsangabe ...<br>";
        file_put_contents("../public/version", $version_to_install);
        chmod("../public/version", 0644);
        chmod("../public/copyright", 0644);
        echo ' ... fertig. ... <br><br>';
    } else {
        echo "</p><p>Das Quellcode-Archiv konnte nicht entpackt werden. Da hat etwas mit dem Download " .
                 "nicht geklappt. Deswegen bricht der Prozess hier ab.</p></p></body></html>";
        exit();
    }
    unlink("src.zip");
    echo "Stelle die vorhandene Konfiguration wieder her ...<br>";
    
    // restore settings of data base connection, app-parameters and colors
    if ($settings_db)
        file_put_contents("../config/settings_db", $settings_db);
    echo "Authentisierungs-API ... ";
    if ($auth_provider_php)
        file_put_contents("../authentication/auth_provider.php", $auth_provider_php);
    echo "Datenbankzugang ... ";
    if ($settings_app)
        file_put_contents("../config/settings_app", $settings_app);
    echo "Einstellungen ... ";
    if ($settings_colors) {
        // restore color set definition
        file_put_contents("../resources/app-colors.txt", $settings_colors);
        // rebuild style sheet from coplor set definition
        $app_style = file_get_contents("../resources/app-style-no_colors.css");
        $app_style_new = $app_style;
        // parse settings ($colors includes font type style)
        $colors = [];
        foreach (explode("\n", $settings_colors) as $color) {
            $key = explode("=", $color)[0];
            $value = explode("=", $color)[1];
            $colors[$key] = $value;
        }
        // restore css from default and settings
        foreach ($colors as $key => $value)
            if (isset($key) && (strlen($key) > 0))
                $app_style_new = str_replace($key, $value, $app_style_new);
        file_put_contents("../resources/app-style.css", $app_style_new);
        echo "Schriftart und Farben ... ";
    }
    
    // adapt history settings, using the updated class definitions.
    include_once '../classes/efa_tables.php';
    include_once '../classes/efa_tools.php';
    $efa_tools = new Efa_tools($toolbox, $socket);
    $upgrade_success = $efa_tools->upgrade_efa_tables();
    if ($upgrade_success === false)
        echo "<b>Fehler</b><br>Das Tabellenlayout konnte nicht angepasst werden. Details siehe '../log/efa_tools.log'.<br>";
    
    // Special case upgrade from 2.3.0_11 and lower: increase the group member size
    // ==============================================================================================
    $update_groups = $socket->query(
            "ALTER TABLE `efa2groups` CHANGE `MemberIdList` `MemberIdList` VARCHAR(9300) NULL DEFAULT NULL;");
    if ($update_groups == false)
        echo "<b>HINWEIS</b>: Konnte die Anzahl der Gruppenmitglieder in der Liste 'efa2groups' leider nicht erweitern. ";
    else
        echo "Anzahl der Gruppenmitglieder in der Liste 'efa2groups' auf maximal 250 erweitert. ";
    
    // Set directories' access rights.
    // ==============================================================================================
    echo "Setze die Zugriffsberechtigung der angelegten Dateistruktur ...<br>";
    $restricted = ["authentication","classes","config","log","uploads"
    ];
    $open = ["api","forms","js","pages","resources","install"
    ];
    foreach ($restricted as $dirname)
        chmod($dirname, 0700);
    foreach ($open as $dirname)
        chmod($dirname, 0755);
    echo ' ... Durchführung fertig.<br></p>';
    
    // Audit result
    // ==============================================================================================
    include_once "../classes/tfyh_audit.php";
    $audit = new Tfyh_audit($toolbox, $socket);
    $audit->run_audit();
    echo '<h5>Überprüfe das Ergebnis</h5><p>Das Audit-Protokoll ist abgelegt unter "../log/app_audit.log":</p><p>';
    include "../classes/init_version.php";
}
?><p>&nbsp;</p>
<p>
	<small>&copy; efacloud - nmichael.de</small>
</p>
<?php
end_script();