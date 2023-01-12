<?php
/**
 * The boathouse client start page.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";
// replace the default menu by the javascript menu version.
// The authentication and access rights provisioning was already done
// within init.
$menu = new Tfyh_menu("../config/access/wmenu", $toolbox);
// set a cookie to tell efaWeb the session and user.
setcookie("tfyhUserID", $_SESSION["User"][$toolbox->users->user_id_field_name], 0);
setcookie("tfyhSessionID", session_id(), 0);

include_once "../classes/efa_config.php";
$efa_config = new Efa_config($toolbox);
// set logbook allowance
$logbook_allowance = ((strcasecmp($_SESSION["User"]["Rolle"], "admin") == 0) ||
         (strcasecmp($_SESSION["User"]["Rolle"], "board") == 0)) ? "all" : "workflow";
// set logbook
$logbook_to_use = $efa_config->current_logbook;
// set name format
$name_format = $efa_config->config["NameFormat"];

// ===== start page output
// start with boathouse header, which includes the set of javascript references needed.
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>
<span style='display: none;' class='current-logbook'
	id='<?php echo $logbook_to_use; ?>'></span>
<span style='display: none;' class='logbook-allowance'
	id='<?php echo $logbook_allowance; ?>'></span>
<span style='display: none;' class='person-id'
	id='<?php echo $_SESSION["User"]["PersonId"]; ?>'></span>
<!-- Projects grid (4 columns, 1 row; images must have the same size)-->
<!-- Display grid (2 columns)-->
<div class='w3-row'>
	<div class="w3-container" id="bths-headerpanel">
		<div class="w3-col l1"></div>
	</div>
</div>
<div class='w3-row'>
	<div class="w3-col l2">
		<div class="w3-container" id="bths-toppanel-left">
			<h4>Verfügbare Boote</h4>
		</div>
		<div class="w3-container" id="bths-mainpanel-left">Die Übersicht über
			die verfügbaren Boote wird aufgebaut, sofern die Berechtigung dafür
			existiert.</div>
	</div>
	<div class="w3-col l2">
		<div class="w3-container" id="bths-toppanel-right">
			<h4>Nicht verfügbare Boote</h4>
		</div>
		<div class="w3-container" id="bths-mainpanel-right">Die Übersicht über
			die nicht verfügbaren Boote wird aufgebaut, sofern die Berechtigung
			dafür existiert.</div>
	</div>
</div>
<div class='w3-row'>
	<div class="w3-col l1">
		<div class="w3-container" id="bths-listpanel-header"></div>
	</div>
	<div class="w3-col l1">
		<div class="w3-container" id="bths-listpanel-list"></div>
	</div>
</div>

<?php
// pass information to Javascript.
// User information
if (isset($_SESSION["User"])) {
    $currentUserAtServer = [];
    foreach ($_SESSION["User"] as $key => $value)
        if (strcasecmp($key, "ecrhis") != 0)
            $currentUserAtServer[$key] = $value;
    $currentUserAtServer["sessionID"] = session_id();
    $script = "\n\n<script>\nvar currentUserAtServer = " .
             json_encode(str_replace("\"", "\\\"", $currentUserAtServer)) . ";\n</script>\n\n";
    // echo $script; Obsolet??
}

echo $efa_config->pass_on_config();
echo file_get_contents('../config/snippets/page_03_footer_bths');