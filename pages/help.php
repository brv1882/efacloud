<?php
/**
 * A page to reset the complete data base.
 * 
 * @author mgSoft
 */

// ===== initialize toolbox and socket and start session.
$user_requested_file = __FILE__;
include_once "../classes/init.php";

// ===== start page output
echo file_get_contents('../config/snippets/page_01_start');
echo $menu->get_menu();
echo file_get_contents('../config/snippets/page_02_nav_to_body');

?>
<!-- START OF content -->
<div class="w3-container">
	<h3>Hilfen in efaCloud</h3>
	<p>EfaCloud bietet eine kontextbezogene Hilfe an, sie wird angezeigt<br>durch das <b>hochgestellte &#9432;</b>. 
	<br>Mit einem Klick auf das &#9432; erscheint der erläuternde Text.</p><p>Im dieser Version gibt es folgende Hilfetexte:</p>
	<ul>
	<?php
$helpdocs = scandir("../helpdocs");
foreach ($helpdocs as $helpdoc) {
    $helpdoc_name = str_replace(".html", "", $helpdoc);
    $info_link = "<sup class='eventitem' id='showhelptext_" . str_replace(".html", "", $helpdoc_name) . "'>&#9432;</sup>";
    if (substr($helpdoc_name, 0, 1) != ".")
        echo "<li>" . $helpdoc_name . $info_link . "</li>";
}
?>
	</ul>
	<p>
		Mehr Information gibt es auf der &gt;&gt;&gt; <a
			href='https://www.efacloud.org' target='_blank'> efaCloud-Webseite
			(öffnet einen neuen Tab).</a>
	</p>
</div>

<?php
end_script();