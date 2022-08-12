<?php
/**
 * Force to run the daily cron jobs
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

include_once ("../classes/cron_jobs.php");
unlink("../log/cronjobs_last_day");
$cronlog_before = file_get_contents("../log/sys_cronjobs.log");
Cron_jobs::run_daily_jobs($toolbox, $socket, $_SESSION["User"][$toolbox->users->user_id_field_name]);
$cronlog_after = file_get_contents("../log/sys_cronjobs.log");
$cronlog_this = substr($cronlog_after, strlen($cronlog_before));

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');
?>

<!-- START OF content -->
<div class="w3-container">
	<h3>Die täglichen Routinen wurden durchgeführt.</h3>
	<p>
<?php
echo str_replace("\n", "<br>", $cronlog_this);

?>
  </p>
</div>
<?php
end_script();