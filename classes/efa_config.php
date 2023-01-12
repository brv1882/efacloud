<?php

class Efa_config
{

    /**
     * The different efa-configurationparts: efa2project.
     */
    public $project = [];

    /**
     * The different efa-configurationparts: efa2types.
     */
    public $types = [];

    /**
     * The different efa-configurationparts: efa2configuration.
     */
    public $config = [];

    /**
     * The compilation of all logbook definitions of all clients as associative array, the index being the
     * book name.
     */
    public $logbooks = [];

    /**
     * The compilation of all clubworkbook definitions of all clients as associative array, the index being
     * the book name.
     */
    public $clubworkbooks = [];

    /**
     * Current logbook provided by the reference client
     */
    public $current_logbook;

    /**
     * Sports year start provided by the reference client
     */
    public $sports_year_start;

    /**
     * tine stamp for the beginnig of the current logbook as provided by the reference client
     */
    public $logbook_start_time;

    /**
     * tine stamp for the end of the current logbook as provided by the reference client
     */
    public $logbook_end_time;

    /**
     * The common toolbox.
     */
    private $toolbox;

    /**
     * a local string builder for the recursive array display function.
     */
    private $str_builder;

    /**
     * a helper variable for the recursive array display function.
     */
    private $row_of_64_spaces = "                                                                ";

    /**
     * Construct the Util class. This parses the efa-configuration passed in the corresponding drectory
     * ../uploads/[efaCloudUserID] into csv files and .
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $this->toolbox = $toolbox;
        $this->load_efa_config();
    }

    /**
     * Compare all client type settings and issue warnings, if not equal.
     * 
     * @return A String containing all issues found. Empty for a complete match at all clients.
     */
    public function compare_client_types ()
    {
        // öload the current configuration as reference
        $this->load_efa_config();
        
        $client_dirs = scandir("../uploads");
        $issues = "";
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $json_filename = "../uploads/" . $client_dir . "/types.json";
                $json_string = file_get_contents($json_filename);
                $client_types_raw = json_decode($json_string, true);
                $client_types = [];
                foreach ($client_types_raw as $type_record) {
                    if (! isset($client_types[$type_record["Category"]]))
                        $client_types[$type_record["Category"]] = [];
                    $client_types[$type_record["Category"]][intval($type_record["Position"])] = [
                            "Type" => $type_record["Type"],"Position" => $type_record["Position"],
                            "Value" => $type_record["Value"]
                    ];
                }
                // parse all reference categories. The set of categories is assumed to be always equal.
                foreach ($this->types as $category => $this_type_records) {
                    if (! isset($client_types[$category]))
                        $issues .= "Für $client_dir feht die Kategorie $category.\n";
                    elseif (! is_array($client_types[$category]) || (count($client_types[$category]) == 0))
                        $issues .= "Für $client_dir ist die Kategorie $category ohne Inhalt.\n";
                    else {
                        // check whether all types of the reference category have a matching type in the
                        // current client.
                        $missing_in_client_types = "";
                        foreach ($this_type_records as $this_type_record) {
                            $matched = false;
                            foreach ($client_types[$category] as $client_type_record)
                                if (strcasecmp($client_type_record["Type"], $this_type_record["Type"]) == 0)
                                    $matched = true;
                            if (! $matched)
                                $missing_in_client_types .= $this_type_record["Type"] . ", ";
                        }
                        if (strlen($missing_in_client_types) > 0)
                            $issues .= "Für $client_dir fehlen in $category : $missing_in_client_types\n";
                        
                        // check whether all types of the current client category have a matching type in the
                        // reference.
                        $missing_in_reference_types = "";
                        foreach ($client_types[$category] as $client_type_record) {
                            $matched = false;
                            foreach ($this_type_records as $this_type_record)
                                if (strcasecmp($this_type_record["Type"], $client_type_record["Type"]) == 0)
                                    $matched = true;
                            if (! $matched)
                                $missing_in_reference_types .= $client_type_record["Type"] . ", ";
                        }
                        if (strlen($missing_in_reference_types) > 0)
                            $issues .= "Für $client_dir gibt es zusätzlich in $category : $missing_in_reference_types\n";
                    }
                }
            }
        }
        return $issues;
    }

    /**
     * Compare all client type configuration settings and issue warnings, if not equal. This will just check a
     * subset of settings, since not all are relevant.
     * 
     * @return A String containing all issues found. Empty for a complete match at all clients.
     */
    public function compare_client_configs ()
    {
        // öload the current configuration as reference
        $this->load_efa_config();
        // values to check for equality. Just a few, add when more is needed.
        $check_names = ["NameFormat","MustEnterDistance","MustEnterWatersForUnknownDestinations"
        ];
        $client_dirs = scandir("../uploads");
        $issues = "";
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $json_filename = "../uploads/" . $client_dir . "/config.json";
                $json_string = file_get_contents($json_filename);
                $client_config_raw = json_decode($json_string, true);
                $client_config = [];
                foreach ($client_config_raw as $client_config_record)
                    $client_config[$client_config_record["Name"]] = $client_config_record["Value"];
                    foreach ($check_names as $check_name)
                    if (strcasecmp(strval($client_config[$check_name]), strval($this->config[$check_name])) !=
                             0)
                        $issues .= "Für $client_dir sind die Werte beim Parameter $check_name nicht identisch.\n";
            }
        }
        return $issues;
    }

    /**
     * collect all logbooks from all clients into a dedicated json file.
     */
    private function compile_books ()
    {
        $client_dirs = scandir("../uploads");
        $logbooks = [];
        $clubworkbooks = [];
        foreach ($client_dirs as $client_dir) {
            if (is_numeric($client_dir)) {
                $json_filename = "../uploads/" . $client_dir . "/project.json";
                $json_string = file_get_contents($json_filename);
                $client_project_cfg = json_decode($json_string, true);
                foreach ($client_project_cfg as $client_project_record) {
                    if (strcasecmp($client_project_record["Type"], "Logbook") == 0)
                        $logbooks[$client_project_record["Name"]] = $client_project_record;
                    if (strcasecmp($client_project_record["Type"], "Clubworkbook") == 0)
                        $clubworkbooks[$client_project_record["Name"]] = $client_project_record;
                }
            }
        }
        file_put_contents("../config/client_cfg/logbooks.json", json_encode($logbooks));
        file_put_contents("../config/client_cfg/clubworkbooks.json", json_encode($clubworkbooks));
    }

    /**
     * This parses the efa-configuration XML-files of the reference client as found in the directory
     * ../uploads/[efaCloudUserID] and stores it as json in the same directory. If ($efaCloudUserID_client ==
     * -1) it is a) also stored in the "../config/client_cfg/" directory and b) relevant values are passed to
     * the config_app configuration and c) the configuration is loaded.
     * 
     * @param int $efaCloudUserID_client
     *            set to the efaCloud UserID of the client to parse. Set to -1 to use configured reference
     *            client.
     * @return string a result message
     */
    public function parse_client_config (int $efaCloudUserID_client = -1)
    {
        // get reference client information
        $cfg = $this->toolbox->config->get_cfg();
        $use_reference_client = ($efaCloudUserID_client < 0);
        $client_to_parse = ($use_reference_client) ? intval($cfg["reference_client"]) : $efaCloudUserID_client;
        if ($client_to_parse <= 0)
            return "no reference client identified"; // all configuration arrays will be empty
        
        $client_files = scandir("../uploads/" . $client_to_parse);
        include_once "../classes/tfyh_xml.php";
        $xml = new Tfyh_xml($this->toolbox);
        $from_to = ["efa2project" => "project","efa2types" => "types","efa2config" => "config"
        ];
        foreach ($client_files as $client_file) {
            foreach ($from_to as $from => $to) {
                if (strpos($client_file, "$from") !== false) {
                    $xml->read_xml(file_get_contents("../uploads/" . $client_to_parse . "/" . $client_file), 
                            false);
                    // TODO remove PHP usage, since 2.3.2_07 / 2.12.2022 obsolete
                    $config_csv = $xml->get_csv("data", "record");
                    file_put_contents("../config/client_cfg/$to.csv", $config_csv);
                    // firstly only Javascript usage
                    $config_array = $xml->get_array("data", "record");
                    if ($use_reference_client)
                        file_put_contents("../config/client_cfg/$to.json", json_encode($config_array));
                    file_put_contents("../uploads/$client_to_parse/$to.json", json_encode($config_array));
                }
            }
        }
        $this->compile_books();
        
        if ($use_reference_client) {
            // reload the configuration
            $this->load_efa_config();
            // some configuration must be transferred into the toolbox application configuration
            $cfg["efa_NameFormat"] = $this->config["NameFormat"];
            $this->toolbox->config->store_app_config($cfg);
        }
        
        return "completed for reference client $client_to_parse";
    }

    /**
     * This loads the efa-configuration files into the respective associative arrays $this->project,
     * $this->types, $this->config.
     * 
     * @param int $efaCloudUserID_client
     *            set to -1 to use the reference client configuration as parsed into "../config/client_cfg/"
     *            directory or to the respective client ID to use the "../uploads/$efaCloudUserID_client/"
     *            directory as source for the configuration json files.
     */
    public function load_efa_config (int $efaCloudUserID_client = -1)
    {
        // file names to load
        $cfg_file_types = ["project","clubworkbooks","logbooks","types","config"
        ];
        $cfg_arrays = [];
        foreach ($cfg_file_types as $cfg_file_type) {
            if ($efaCloudUserID_client < 0)
                $json_filename = (file_exists("../config/client_cfg/$cfg_file_type.json")) ? "../config/client_cfg/$cfg_file_type.json" : "../config/client_cfg_default/$cfg_file_type.json";
            else
                $json_filename = "../uploads/$efaCloudUserID_client/$cfg_file_type.json";
            $json_string = file_get_contents($json_filename);
            $cfg_arrays[$cfg_file_type] = json_decode($json_string, true);
        }
        
        // load the project configuration json
        $this->project = [];
        foreach ($cfg_arrays["project"] as $project_record) {
            if (! isset($this->project[$project_record["Type"] . "s"]))
                $this->project[$project_record["Type"] . "s"] = [];
            $this->project[$project_record["Type"] . "s"][] = $project_record;
        }
        // load logbooks
        $this->logbooks = $cfg_arrays["logbooks"];
        $this->clubworkbooks = $cfg_arrays["clubworkbooks"];
        
        // load the types json
        $this->types = [];
        foreach ($cfg_arrays["types"] as $type_record) {
            if (! isset($this->types[$type_record["Category"]]))
                $this->types[$type_record["Category"]] = [];
            $this->types[$type_record["Category"]][intval($type_record["Position"])] = [
                    "Type" => $type_record["Type"],"Position" => $type_record["Position"],
                    "Value" => $type_record["Value"]
            ];
        }
        // sort
        foreach ($this->types as $category => $unsorted) {
            ksort($unsorted);
            $this->types[$category] = $unsorted;
        }
        
        // load the client configuration json
        $this->config = [];
        foreach ($cfg_arrays["config"] as $config_record) {
            $this->config[$config_record["Name"]] = (isset($config_record["Value"])) ? $config_record["Value"] : "";
        }
        
        // assign the direct variables
        // default is the srver configuration setting
        $this->current_logbook = str_replace("JJJJ", date("Y"), 
                $this->toolbox->config->get_cfg()["current_logbook"]);
        // overwrite it with the reference client current logbook, if available.
        if (isset($this->project["Boathouses"][0]["CurrentLogbookEfaBoathouse"]))
            $this->current_logbook = $this->project["Boathouses"][0]["CurrentLogbookEfaBoathouse"];
        // find the the reference client's sports year start via the logbook.
        $logbook_period = $this->get_book_period($this->current_logbook, true);
        $this->logbook_start_time = $logbook_period["start_time"];
        $this->logbook_end_time = $logbook_period["end_time"];
        $this->sports_year_start = $logbook_period["sports_year_start"];
    }

    /**
     * Return the start and end time (PHP seconds) and the sports year start for a specific logbook.
     * 
     * @param String $book_name
     *            the name of the logbook or clubworkbook
     * @param bool $is_logbook
     *            set true for a logbook to check and false for a clubworkbook
     * @return array with the fields "start_time" (seconds since epoch), "end_time" (seconds since epoch),
     *         "sports_year_start" (month, 1 .. 12), "book_matched" (bool)
     */
    public function get_book_period (String $book_name, bool $is_logbook)
    {
        $ret = ["book_matched" => false
        ];
        // find the the reference client's sports year start via the logbook.
        $sports_year_start = false;
        $book = false;
        if ($is_logbook) {
            if (isset($this->logbooks) && isset($this->logbooks[$book_name]))
                $book = $this->logbooks[$book_name];
        } else {
            if (isset($this->clubworkbooks) && isset($this->clubworkbooks[$book_name]))
                $book = $this->clubworkbooks[$book_name];
        }
        
        file_put_contents("../log/tmp", $book_name . "\n" . json_encode($this->logbooks) . "\n", FILE_APPEND);
        
        if ($book !== false) {
            $ret["book_matched"] = true;
            // start of day
            $ret["start_time"] = strtotime($this->toolbox->check_and_format_date($book["StartDate"]));
            // end of day
            $ret["end_time"] = strtotime($this->toolbox->check_and_format_date($book["EndDate"])) + 22 * 3600;
            $sports_year_start = substr($book["StartDate"], 0, 6);
        }
        // if failed, use the server confiuration setting
        if ($is_logbook && ($sports_year_start == false)) {
            $sports_year_start = $this->toolbox->config->get_cfg()["sports_year_start"];
            $current_year = intval(date("Y"));
            $logbook_start_time = strtotime($current_year . "-" . $this->sports_year_start . "-1");
            $next_year = $current_year + 1;
            $logbook_end_time = strtotime($next_year . "-" . $this->sports_year_start . "-1") - 2 * 3600;
        }
        $ret["sports_year_start"] = $sports_year_start;
        
        file_put_contents("../log/tmp", json_encode($ret) . "\n", FILE_APPEND);
        
        return $ret;
    }

    /**
     * Recursive html display of an array using the &lt;ul&gt; list type.
     * 
     * @param array $a
     *            the array to display
     * @param int $level
     *            the recursion level. To start the recursion, use 0 or leave out.
     */
    public function display_array (array $a, int $level = 0)
    {
        if ($level == 0)
            $this->str_builder = "";
        $indent = substr($this->row_of_64_spaces, 0, $level * 2);
        $this->str_builder .= $indent . "<ul>\n";
        $indent .= " ";
        foreach ($a as $key => $value) {
            $this->str_builder .= $indent . "<li>";
            if (is_array($value)) {
                $this->str_builder .= $key . "\n";
                $this->display_array($value, $level + 1);
            } elseif (is_object($value))
                $this->str_builder .= "$key : [object]";
            else
                $this->str_builder .= "$key : $value";
            $this->str_builder .= "</li>\n";
        }
        $this->str_builder .= $indent . "</ul>\n";
        if ($level == 0)
            return $this->str_builder;
    }

    /**
     * Recursive text display of an array using the &lt;ul&gt; list type.
     * 
     * @param array $a
     *            the array to display
     * @param String $indent0
     *            the indentation at level 0
     * @param int $level
     *            the recursion level. To start the recursion, use 0 or leave out. Do not use any other value!
     */
    public function display_array_text (array $a, String $indent0, int $level = 0)
    {
        if ($level == 0)
            $this->str_builder = "";
        $indent = substr($this->row_of_64_spaces, 0, $level * 2);
        $indent .= " ";
        foreach ($a as $key => $value) {
            $this->str_builder .= $indent0 . $indent;
            if (is_array($value)) {
                $this->str_builder .= $key . ":\n";
                $this->display_array_text($value, $indent0, $level + 1);
            } elseif (is_object($value))
                $this->str_builder .= "$key: [object]" . "\n";
            else
                $this->str_builder .= "$key: $value" . "\n";
        }
        if ($level == 0)
            return $this->str_builder;
    }

    /**
     * Provide a script entry to pass the efa client configuration to efaWeb or the efaCloud javascript
     * environment. Here: single array
     * 
     * @return string the html code t include in the respective file.
     */
    private function json_file_to_script (String $cfg_filename, String $cfg_varname)
    {
        // read configuration as was stored by the client
        $config_file = "../config/client_cfg/" . $cfg_filename;
        if (file_exists($config_file))
            $config_contents = file_get_contents($config_file);
        // on no success read default
        if (! file_exists($config_file) || (strlen($config_contents) < 10)) {
            $config_file_default = "../config/client_cfg_default/" . $cfg_filename;
            $config_contents = file_get_contents($config_file_default);
        }
        return "<script>\nvar $cfg_varname = " . $config_contents . ";\n" . "</script>\n";
    }

    /**
     * Provide a script entry to pass the efa client configuration to efaWeb or the efaCloud javascript
     * environment. Here: full configuration
     * 
     * @return string the html code t include in the respective file.
     */
    public function pass_on_config ()
    {
        $html = "";
        $html .= $this->json_file_to_script("types.json", "efaTypes");
        $html .= $this->json_file_to_script("project.json", "efaProjectCfg");
        $html .= $this->json_file_to_script("config.json", "efaConfig");
        $html .= "<script>\nvar efaCloudCfg = " . json_encode($this->toolbox->config->get_cfg()) . ";\n" .
                 "</script>\n";
        return $html;
    }

    /**
     * Get the last access of every API client
     * 
     * @param Tfyh_socket $socket
     *            the common data base access socket
     * @param bool $html
     *            set true to get html encoded output, false for plain text
     * @param bool $contentsize
     *            set true to get the contentsize table, false to omit.
     * @return string the last access information on every client.
     */
    public function get_last_accesses_API (Tfyh_socket $socket, bool $html, bool $contentsize)
    {
        $clients = scandir("../log/lra");
        $active_clients_txt = "";
        $active_clients_html = "";
        foreach ($clients as $client) {
            if (($client != ".") && ($client != "..")) {
                $client_record = $socket->find_record("efaCloudUsers", "efaCloudUserID", $client);
                if ($client_record !== false) {
                    $active_client_txt = $client_record["Vorname"] . " " . $client_record["Nachname"] . " (#" .
                             $client_record["efaCloudUserID"] . ", " . $client_record["Rolle"] .
                             "), letzte Aktivität: " . file_get_contents("../log/lra/" . $client);
                    $active_clients_txt .= $active_client_txt . "\n";
                    $active_clients_html .= "<p>" . $active_client_txt . "</p>";
                    $is_boathouse = (strcasecmp($client_record["Rolle"], "bths") == 0);
                    if ($contentsize && $is_boathouse && file_exists("../log/contentsize/" . $client)) {
                        $active_client_table = trim(file_get_contents("../log/contentsize/" . $client));
                        $active_clients_txt .= $active_client_table . "\n";
                        $active_clients_html .= "<table><tr><td>" . str_replace("\n", "</td></tr><tr><td>", 
                                str_replace(";", "</td><td>", $active_client_table)) . "</td></tr></table>";
                    }
                }
            }
        }
        return ($html) ? $active_clients_html : $active_client_txt;
    }
}    