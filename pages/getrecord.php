<?php

/**
 * Title: efa - elektronisches Fahrtenbuch für Ruderer Copyright: Copyright (c) 2001-2022 by Nicolas Michael
 * Website: http://efa.nmichael.de/ License: GNU General Public License v2. Module efaCloud: Copyright (c)
 * 2020-2021 by Martin Glade Website: https://www.efacloud.org/ License: GNU General Public License v2
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
if (! isset($_GET["table"])) {
    echo "Fehler\nBei der Abfrage wurde kein Tabellenname angegeben.";
    exit();
} elseif (! isset($_GET["ecrid"])) {
    echo "Fehler\nBei der Abfrage wurde keine efacloud record Id (ecrid) angegeben.";
    exit();
}

$record = $socket->find_record($_GET["table"], "ecrid", $_GET["ecrid"]);
if ($record == false) {
    echo "Fehler\nDer Datensatz in der Tabelle '" . $_GET["table"] . "' mit der ecrid '" . $_GET["ecrid"] .
             "' existiert nicht.";
    exit();
}

foreach ($record as $key => $value) {
    if (strcmp($key, "ecrhis") !== 0)
        echo "<b>$key</b>: $value<br>\n";
}
if (isset($record["ecrhis"])) {
echo "<hr><b>Historie</b>";
echo $socket->get_history_html($record["ecrhis"]);
}
end_script(false);