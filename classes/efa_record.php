<?php
include_once "../classes/efa_tables.php";
include_once '../classes/tfyh_list.php';

/**
 * class file for the efaCloud data verification and modification. This class adds to the Efa_tables class
 * whih deifnes tables type semantics and contains static checker functions.
 */
class Efa_record
{

    /**
     * Column names of those columns that must not be empty except those, which reflect a data key. Merge
     * $assert_not_empty_fields = array_merge(Efa_tables::$efa_data_key_fields[$table_name],
     * self::$assert_not_empty_fields[$table_name]); to get full set per table. MAde public for the
     * Efa_tables::clear_record_for_delete() function to use.
     */
    public static $assert_not_empty_fields = ["efa2autoincrement" => [],
            "efa2boatdamages" => ["Severity"
            ],"efa2boatreservations" => ["Type"
            ],"efa2boats" => ["Name"
            ],"efa2boatstatus" => [],"efa2clubwork" => ["PersonId","Date","Description","Hours"
            ],"efa2crews" => ["Name"
            ],"efa2destinations" => ["Name"
            ],"efa2groups" => ["Name"
            ],"efa2fahrtenabzeichen" => [],"efa2logbook" => ["Date"
            ], // ,"Logbookname" is a key field
"efa2messages" => [],"efa2sessiongroups" => ["Logbook","Name","StartDate","EndDate"
            ],
            "efa2persons" => ["StatusId","Gender" // "FirstLastName" is a system field and will be created.
            ],"efa2statistics" => ["Name","Position"
            ],"efa2status" => ["Name"
            ],"efa2waters" => ["Name"
            ], // the following tables shall not be edited by efacloud
"efa2project" => [],"efa2admins" => ["Name","Password"
            ],"efa2types" => ["Category","Type","Value"
            ]
    ];

    /**
     * Column names of those columns that must be unique, additionally to the key fields. If two key fields
     * ANDed must be unique, they are separated by a dot.
     */
    public static $assert_unique_fields = ["efa2autoincrement" => [],
            "efa2boatdamages" => ["Damage.BoatId"
            ],"efa2boatreservations" => ["Reservation.BoatId"
            ],"efa2boatstatus" => [],"efa2boats" => [],"efa2clubwork" => [],"efa2crews" => ["Name"
            ],"efa2destinations" => [],"efa2fahrtenabzeichen" => [],"efa2groups" => [],
            "efa2logbook" => ["EntryId.Logbookname"
            ],"efa2messages" => [],"efa2persons" => [],"efa2sessiongroups" => ["Name.Logbook"
            ],"efa2statistics" => ["Name","Position"
            ],"efa2status" => ["Name"
            ],"efa2waters" => ["Name"
            ]
    ];

    public static $allow_web_delete = ["efa2boatdamages","efa2boatstatus","efa2boatreservations",
            "efa2clubwork","efa2crews","efa2groups","efa2logbook","efa2messages","efa2sessiongroups",
            "efa2status"
    ];

    /**
     * The list indices for UUID referencing. Use $this->build_indices to initialize respective associative
     * arrays
     */
    private static $uuid_list_id = ["efa2boats" => 1,"efa2clubwork" => 2,"efa2crews" => 3,
            "efa2destinations" => 4,"efa2groups" => 5,"efa2persons" => 6,"efa2sessiongroups" => 7,
            "efa2statistics" => 8,"efa2status" => 9,"efa2waters" => 10
    ];

    /**
     * The data base connection socket.
     */
    private $socket;

    /**
     * The generic toolbox.
     */
    private $toolbox;

    /**
     * For a bulk operation collect first all names and their ids to speed up uniqueness and references checks
     */
    private $ids_for_names = array();

    /**
     * collect the ecrids latest valid record per UUID for bulk updates.
     */
    private $table_names = array();

    /**
     * public Constructor.
     * 
     * @param Tfyh_toolbox $toolbox
     *            application toolbox
     * @param Tfyh_socket $socket
     *            the socket to connect to the database
     */
    public function __construct (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        $this->toolbox = $toolbox;
        $this->socket = $socket;
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- HELPER FUNCTIONS ------------------------------------------------------ */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Checks whether the field does not contain a relevant field, i.e. no field which is neither
     * $this->efa_system_fields nor Efa_tables::$efa_data_key_fields[$tablename.
     * 
     * @param array $tablename
     *            the table out of which the record shall be deleted.
     * @param array $record
     *            record which shall be deleted.
     * @return true, if no used field was found except keys and history. Else false
     */
    public static function is_content_empty (String $tablename, array $record)
    {
        foreach ($record as $key => $value) {
            // efa key fields are always set, no real content
            $is_relevant_field = isset(Efa_tables::$efa_data_key_fields[$tablename]) &&
                     ! in_array($key, Efa_tables::$efa_data_key_fields[$tablename]);
            // special case InvalidFrom: no key, no system field, but no real content
            $is_relevant_field = $is_relevant_field && (strcmp($key, "InvalidFrom") != 0);
            // efaCloud record management fields are always set, no real content
            $is_relevant_field = $is_relevant_field && ! in_array($key, Efa_tables::$ecr_system_field_names);
            // fields which are set by efa as system fields are no real content
            $is_relevant_field = $is_relevant_field && ! in_array($key, Efa_tables::$efa_system_field_names);
            // fields which must not be empty are always set, no real content
            $is_relevant_field = $is_relevant_field && isset(self::$assert_not_empty_fields[$tablename]) &&
                     ! in_array($key, self::$assert_not_empty_fields[$tablename]);
            // efa virtual fields are automatically set, no real content
            $is_relevant_field = $is_relevant_field && isset(Efa_tables::$virtual_fields[$tablename]) &&
                     ! in_array($key, Efa_tables::$virtual_fields[$tablename]);
            $is_empty_field = is_null($value) || (strlen($value) == 0) || (isset(
                    Efa_tables::$int_fields[$tablename]) && in_array($key, Efa_tables::$int_fields[$tablename]) &&
                     (intval($value) == 0));
            if ($is_relevant_field && ! $is_empty_field)
                return false;
        }
        return true;
    }

    /* --------------------------------------------------------------------------------------- */
    /* --------------- PREMODIFICATION CHECKS AND CORRECTIONS -------------------------------- */
    /* --------------------------------------------------------------------------------------- */
    
    /**
     * Initialize the arrays for checking in bulk operations.
     * 
     * @param int $list_id
     *            the id of the list within the set "../config/lists/efaAuditUUIDnames" which shall be used to
     *            identify the most recent record version.
     * @param bool $force_refresh
     *            set true to force a refeesh, even if the index was already build.
     */
    private function build_indices (int $list_id, bool $force_refresh)
    {
        if (! $force_refresh &&
                 (isset($this->ids_for_names[$list_id]) && (count($this->ids_for_names[$list_id]) > 0)))
            return;
        // clear index
        $this->ids_for_names[$list_id] = [];
        $uuid_names = new Tfyh_list("../config/lists/efaAuditUUIDnames", $list_id, "", $this->socket, 
                $this->toolbox);
        $this->table_names[$list_id] = $uuid_names->get_table_name();
        $col_uuid = $uuid_names->get_field_index("Id");
        $col_ecrid = $uuid_names->get_field_index("ecrid");
        $col_invalidFrom = $uuid_names->get_field_index("InvalidFrom");
        foreach ($uuid_names->get_rows() as $row) {
            $uuid = $row[0];
            // build name index
            if ($list_id == self::$uuid_list_id["efa2persons"]) // Special case persons' name
                $name = $row[1] . " " . $row[2];
            else
                $name = $row[1]; // includes names_clubwork
            $this->ids_for_names[$list_id][$name] = $uuid;
        }
    }

    /**
     * Check whether needed fields are all set, and return an error, if not.
     * 
     * @param array $record_to_check
     *            the record which shall be checked, mapped and completed.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @param int $mode
     *            Set to mode of operations: 1 = insert, 2 = update.
     * @return string in case of errors a String with the error message, else an empty String
     */
    public function check_unique_and_not_empty (array $record_to_check, String $tablename, int $mode)
    {
        // check for insertion of a new or a copy of a record after delimitation, that no needed fields are
        // empty.
        $assert_not_empty = array_merge(Efa_tables::$efa_data_key_fields[$tablename], 
                self::$assert_not_empty_fields[$tablename]);
        if ($mode == 1) {
            foreach ($assert_not_empty as $not_empty_field) {
                if (! isset($record_to_check[$not_empty_field]) || is_null($record_to_check[$not_empty_field]) ||
                         (strlen($record_to_check[$not_empty_field]) == 0)) {
                    // field not set. Maybe it will be generated later.
                    if (! in_array($not_empty_field, Efa_tables::$server_gen_fields[$tablename]))
                        return "Das erforderliche Feld '$not_empty_field' darf bei neuen Datensätzen nicht leer sein.";
                }
            }
        }
        // Check uniqueness of all relevant fields or field combinations for all cases.
        // prepare list for cross check
        if (isset(self::$assert_unique_list_id[$tablename])) {
            $assert_unique_list_id = self::$assert_unique_list_id[$tablename];
            $assert_unique_list = new Tfyh_list("../config/lists/efaAuditDuplicates", $assert_unique_list_id, 
                    "", $this->socket, $this->toolbox);
            // prepare lookup indices for list entries and previous record (for updates)
            $assert_unique_fields = self::$assert_unique_fields[$tablename];
            $col_ecrid = $assert_unique_list->get_field_index("ecrid");
            $col_id = $assert_unique_list->get_field_index("Id");
            $previous_record = ($mode == 2) ? $this->socket->find_record($tablename, "ecrid", 
                    $record_to_check["ecrid"]) : false;
            // prepare references to check whether the duplicate is actually the very same.
            $reference_ecrid = (isset($record_to_check["ecrid"])) ? $record_to_check["ecrid"] : "";
            $reference_id = (isset($record_to_check["Id"])) ? $record_to_check["Id"] : "";
            $is_versionized_table = in_array($tablename, Efa_tables::$versionized_table_names);
            // screen through fields to be asserted as unique.
            foreach ($assert_unique_fields as $assert_unique_field) {
                // per field compile first the reference value, which shall be checked for duplicates
                $parts = explode(".", $assert_unique_field);
                $reference = "";
                $cols = [];
                foreach ($parts as $part) {
                    $cols[] = $assert_unique_list->get_field_index($part);
                    if (! isset($record_to_check[$part])) {
                        // if the value is not set in the new record (e.g. for updates) use the previous one
                        if ($previous_record !== false)
                            $reference .= $previous_record[$part] . ".";
                        else
                            return "Dem Datensatz fehlt das auf Eindeutigkeit zu prüfende Feld '" .
                                     $assert_unique_field . "'. " . " <a class='eventitem' id='viewrecord_" .
                                     $tablename . "_" . $record_to_check["ecrid"] . "'>ansehen</a>";
                    } else
                        $reference .= $record_to_check[$part] . ".";
                }
                // search for this field in all records.
                foreach ($assert_unique_list->get_rows() as $row) {
                    $compare = "";
                    foreach ($cols as $col)
                        $compare .= $row[$col] . ".";
                    if (strcasecmp($reference, $compare) == 0) {
                        // a match was found. Now verify, whether it is the record's self or a duplicate.
                        if ((strcmp($row[$col_ecrid], $reference_ecrid) != 0) && ! $is_versionized_table)
                            return "Das eindeutige Feld '" . $assert_unique_field .
                                     "' ist nicht eindeutig. Weiteres Vorkommen im Datensatz mit ecrid '" .
                                     $row[$col_ecrid] . "'" . " <a class='eventitem' id='viewrecord_" .
                                     $tablename . "_" . $row[$col_ecrid] . "'>ansehen</a>";
                        if ((strcmp($row[$col_id], $reference_id) != 0) && $is_versionized_table)
                            return "Das eindeutige Feld '" . $assert_unique_field .
                                     "' ist nicht eindeutig. Weiteres Vorkommen im Objekt mit Id '" .
                                     $row[$col_id] . "'" . " <a class='eventitem' id='viewrecord_" . $tablename .
                                     "_" . $row[$col_ecrid] . "'>ansehen</a>";
                    }
                }
            }
        }
        return "";
    }

    /**
     * Map boolean 'on' from forms to 'true' for efa. Check the field value after mapping to be 'true',
     * 'false' or ''.
     * 
     * @param array $record
     *            the record which shall be checked and mapped.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @return string|array the mapped record or in case of errors a String with the error message
     */
    private function map_and_check_bool_fields (array $record, String $tablename)
    {
        // boolean fields check and mapping of 'on' to 'true'
        if (isset(Efa_tables::$boolean_fields[$tablename]))
            foreach (Efa_tables::$boolean_fields[$tablename] as $boolean_field) {
                if (isset($record[$boolean_field]) && (strlen($record[$boolean_field]) > 0)) {
                    if (strcasecmp($record[$boolean_field], "on") == 0)
                        $record[$boolean_field] = "true";
                    $is_true_or_false = (strcasecmp($record[$boolean_field], "true") == 0) ||
                             (strcasecmp($record[$boolean_field], "false") == 0);
                    if ((strlen($record[$boolean_field]) > 0) && ! $is_true_or_false) {
                        $res = "Das Datenfeld '$boolean_field' darf nur 'true'," .
                                 " 'false' oder nichts enthalten, der Wert '" . $record[$boolean_field] .
                                 "' ist ungültig.";
                        return $res;
                    }
                }
            }
        return $record;
    }

    /**
     * Check integer fields for being really an integer number.
     * 
     * @param array $record
     *            the record which shall be checked and mapped.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @return string|array the mapped record or in case of errors a String with the error message
     */
    private function check_int_fields (array $record, String $tablename)
    {
        // integer fields check, if they meet the format.
        foreach (Efa_tables::$int_fields[$tablename] as $int_field) {
            if (isset($record[$int_field]) && ! is_numeric($record[$int_field])) {
                return "Das Datenfeld '$int_field' muss einen Zahlwert enthalten, der Wert '" .
                         $record[$int_field] . "' ist ungültig.";
            }
        }
        return $record;
    }

    /**
     * Map names to Ids and extra fields ValidFromDate, InvalidFromDate to timestamps and StatusName to
     * StatusId. Removes the extra name fields
     * 
     * @param array $record
     *            the record which shall be checked and mapped.
     * @param String $tablename
     *            the tables name to know which fields are system and bool fields.
     * @return array the mapped record
     */
    private function map_and_remove_extra_name_fields (array $record, String $tablename)
    {
        // Map the extra date fields
        if ((isset($record["ValidFromDate"])) && (strlen($record["ValidFromDate"]) > 0)) {
            $record["ValidFrom"] = strtotime($this->toolbox->check_and_format_date($record["ValidFromDate"])) .
                     "000";
        }
        if ((isset($record["InvalidFromDate"])) && (strlen($record["InvalidFromDate"]) > 0)) {
            $record["InvalidFrom"] == strtotime(
                    $this->toolbox->check_and_format_date($record["InvalidFromDate"])) . "000";
        }
        // Map the extra status name field
        if (isset($record["StatusName"]) && (strlen($record["StatusName"]) > 0)) {
            $status_list_id = self::uuid_list_id["efa2status"];
            $this->build_indices($status_list_id, false);
            if (! isset($this->ids_for_names[$status_list_id][$record["StatusName"]]))
                return "Für den Status '" . $record["StatusName"] . "' wurde keine Id gefunden.";
            $statusId = $this->ids_for_names[$status_list_id][$record["StatusName"]];
            $record["StatusId"] = $statusId;
        }
        // remove all fields, even if they were empty
        unset($record["ValidFromDate"]);
        unset($record["InvalidFromDate"]);
        unset($record["StatusName"]);
        return $record;
    }

    /**
     * Check all virtual fields whether an entry is missing. If so, add it.
     * 
     * @param int $app_user_id
     *            the user which will be enter, when applying the change
     * @return string a result String, empty, if nothing had to be done.
     */
    public function check_and_add_empty_virtual_fields (int $app_user_id)
    {
        include_once "../classes/tfyh_list.php";
        $vf_list = new Tfyh_list("../config/lists/efaAuditVirtualFields", 0, "", $this->socket, $this->toolbox);
        $vf_list_definitions = $vf_list->get_all_list_definitions();
        $result = "";
        foreach ($vf_list_definitions as $vf_list_definition) {
            $vf_list = new Tfyh_list("../config/lists/efaAuditVirtualFields", $vf_list_definition["id"], "", 
                    $this->socket, $this->toolbox);
            $corrected = 0;
            $failed = 0;
            $tablename = $vf_list->get_table_name();
            $rows_to_correct = $vf_list->get_rows();
            foreach ($rows_to_correct as $row_to_correct) {
                $matching_key = ["ecrid" => $row_to_correct[0]
                ];
                $record_to_modify = $this->socket->find_record_matched($tablename, $matching_key);
                $record_to_modify_plus_vf = Efa_tables::add_virtual_fields($record_to_modify, $tablename, 
                        $this->toolbox, $this->socket);
                if ($record_to_modify_plus_vf !== false) {
                    $update_result = $this->socket->update_record_matched($app_user_id, $tablename, 
                            $matching_key, $record_to_modify_plus_vf);
                    if (strlen($update_result) == 0)
                        $corrected ++;
                    else
                        $failed ++;
                    $whereisit .= "u:" . $update_result . "-c:" . $corrected . " - ";
                }
            }
            if (($corrected + $failed) > 0)
                $result .= $tablename . " (" . $corrected . "/" . ($corrected + $failed) . "), ";
            else
                $result .= "";
        }
        if (strlen($result) == 0)
            return "none";
        return $result;
    }

    /**
     * Allow for a modification of a versionized record. The process is a. Check whether the object in
     * question exists. Try ecrid or UUID, if given, or resolve the name as last resort. b. Check whether the
     * record existance fits to the intended operation. Refuse if: b1) if the object exists and mode is
     * 'insert', b2) the object does not exist and the mode is 'update', b3) the object exists, but has no
     * valid record and the mode is 'update' and there are changes beyond the 'InvalidFrom' field.
     * 
     * @param int $list_id
     *            the id of the list within the set "../config/lists/efaAuditUUIDnames" which shall be used to
     *            identify the most recent record version. You may use self::$uuid_list_id[$tablename] to get
     *            it.
     * @param array $version_record
     *            the record to insert as new, update as existing or insert as new version
     * @param int $mode
     *            Set 1 for insert, 2 for update.
     * @param bool $force_refresh
     *            set true to force an index refresh, even if the index was already build.
     * @return String|array an error message for user display on all errors, else the $version_record with the
     *         correct ecrid.
     */
    private function check_against_existing (int $list_id, array $version_record, int $mode, 
            bool $force_refresh)
    {
        
        // a. Check whether the object in question exists. Try ecrid or UUID, if given, or resolve the name
        // else
        $this->build_indices($list_id, $force_refresh);
        $tablename = $this->table_names[$list_id];
        $checked_for = "";
        
        // get the existing record first
        $existing_record = false;
        if ((isset($version_record["ecrid"])) && (strlen($version_record["ecrid"]) > 0)) {
            // ecrid is unique. Get UUID and check whether it is contained in the UUID list.
            $checked_for = "mit der Datensatz Id '" . $version_record["ecrid"] . "'";
            $existing_record = $this->socket->find_record_matched($tablename, 
                    ["ecrid" => $version_record["ecrid"]
                    ]);
        } else { // find newest existing for the provided Id or name
            if (((isset($version_record["Id"])) && (strlen($version_record["Id"]) > 0))) {
                // if an Id is given, use the Id
                $try_uuid = $version_record["Id"];
                $checked_for = "mit der Objekt Id '" . $try_uuid . "'";
            } else {
                // last resort: the name resolution
                $name = Efa_tables::get_name($tablename, $version_record);
                $checked_for = "mit dem Namen '" . $name . "'";
                $try_uuid = (isset($this->ids_for_names[$list_id][$name])) ? $this->ids_for_names[$list_id][$name] : "----";
            }
            $object_last_records = $this->socket->find_records_sorted_matched($tablename, 
                    ["Id" => $try_uuid
                    ], 1, "=", "InvalidFrom", false);
            if ($object_last_records !== false)
                $existing_record = $object_last_records[0];
            // for update and delimit now settle the record to use.
        }
        
        // b. Check whether the record existance fits to the intended operation
        $error_prefix = "Ein Objekt $checked_for gibt es in $tablename " .
                 (($mode == 1) ? "schon. " : "noch nicht. ");
        if ($mode == 1) {
            if ($existing_record !== false) {
                // version periods must be one after the other without gap or overlap.
                $existing_invalid_from32 = (isset($existing_record["InvalidFrom"])) ? Efa_tables::value_validity32(
                        $existing_record["InvalidFrom"]) : 0;
                $version_record_valid_from32 = (isset($version_record["ValidFrom"])) ? Efa_tables::value_validity32(
                        $version_record["ValidFrom"]) : 0;
                if (($version_record_valid_from32 - $existing_invalid_from32) > 120)
                    return $error_prefix .
                             " Die neue Version schließt nicht an es an (Abweichung > 2 Minuten).";
                if (($version_record_valid_from32 - $existing_invalid_from32) < - 120)
                    return $error_prefix .
                             " Die neue Version überlappt mit der bestehenden um mehr als 2 Minuten.";
            }
        } else {
            if ($existing_record === false)
                return $error_prefix . " noch nicht. Es kann nicht aktualisiert werden.";
            elseif (! isset($version_record["ecrid"]) || (strlen($version_record["ecrid"]) == 0))
                $version_record["ecrid"] = $existing_record["ecrid"];
            $invalidFrom = Efa_tables::value_validity32($existing_record["InvalidFrom"]);
            if (time() > $invalidFrom) {
                // for invalid versions only the InvalidFrom may be changed. Check, whether this is the case
                $has_changes = false;
                foreach ($version_record as $key => $value) {
                    if ((strcmp($value, strval($existing_record[$key])) !== 0) &&
                             (strcmp($key, "InvalidFrom") !== 0)) {
                        $has_changes = true;
                    }
                }
                if ($has_changes) {
                    return $error_prefix . " Es hat aber keinen aktuell güligen Datensatz mehr. " .
                             "Es kann nicht aktualisiert werden. " .
                             "Dazu muss erst die letzte vorhandene Version wieder gültig gesetzt werden.";
                }
            }
        }
        return $version_record;
    }

    /**
     * Check the semantic validity of data fields in non versionized records, depending on the table's
     * constraints.
     * 
     * @param String $tablename
     *            the table's name for the constraints to use.
     * @param array $record
     *            the record to check.
     * @param int $mode
     *            Set 1 for insert, 2 for update
     * @param int $api_version
     *            API-version of the client request. For API-version 1, 2 only the dates for logbook and
     *            clubworkbook are checked. For API-version >= 3 the insert is checked before execution and
     *            possibly rejected with an error message.
     * @return array|String the record with possibly the modified Membership field (efa2status only) or en
     *         error message.
     */
    private function check_efa_semantic_validity (String $tablename, array $record, int $mode, 
            int $api_version)
    {
        if (strcasecmp($tablename, "efa2logbook") == 0) {
            // Step 1: make sure both start and end date are within logbook range
            // This is for all API levels, because logbook changes in efa without reboot
            // communicate the wrong logbook.
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($this->toolbox);
            $efa_config->load_efa_config();
            $logbook_name = $record["Logbookname"];
            $logbook_period = $efa_config->get_book_period($logbook_name, true);
            if ($logbook_period["book_matched"] === false)
                return "Das Fahrtenbuch $logbook_name ist nicht bekannt.";
            $logbook_start = $logbook_period["start_time"];
            $logbook_end = $logbook_period["end_time"];
            $entry_start = strtotime($record["Date"]);
            $entry_end = (isset($record["EndDate"]) && (strlen($record["EndDate"]) > 4)) ? strtotime(
                    $record["EndDate"]) : $entry_start;
            $error_message = "Die Fahrt mit Beginn " . date("d.m.Y", $entry_start) . " und Ende " .
                     date("d.m.Y", $entry_end) .
                     " liegt nicht im gültigen Zeitraum für das Fahrtenbuch '$logbook_name' (" .
                     date("d.m.Y", $logbook_start) . " - " . date("d.m.Y", $logbook_end) . ")";
            if (($entry_start < $logbook_start) || ($entry_start > $logbook_end) ||
                     ($entry_end < $logbook_start) || ($entry_end > $logbook_end))
                return $error_message;
            // entries maximum 5 days in advance. (efaCloud Check only.)
            if ($entry_start > (time() + 5 * 86400))
                return "Ungültiges Startdatum: " . $record["Date"] .
                         ". Fahrten können maximal 5 Tage im Voraus eingetragen werden.";
            // make sure enddate is after startdate
            if ($entry_end < $entry_start)
                return "Das Endedatum '" . $record["EndDate"] . "' liegt vor dem Startdatum '" .
                         $record["Date"] . "'.";
            // the following checks are only needed for API level 3 and higher
            if ($api_version >= 3) {
                // make sure that the entry's date fits into the selected session group
                if (isset($record["SessionGroupId"]) && (strlen($record["SessionGroupId"]) > 0)) {
                    $session_group = $this->socket->find_record_matched("efa2sessiongroups", 
                            ["Id" => $record["SessionGroupId"]
                            ]);
                    if ($session_group !== false) {
                        $session_group_start = strtotime($session_group["StartDate"]);
                        $session_group_end = strtotime($session_group["EndDate"]);
                        $session_group_name = $session_group["Name"];
                        if (($entry_start < $session_group_start) || ($entry_start > $session_group_end) ||
                                 ($entry_end < $session_group_start) || ($entry_end > $session_group_end))
                            return "Die Fahrt liegt nicht im gültigen Zeitraum für die Fahrtengruppe '$session_group_name'.";
                    }
                }
                // select boat variant, if empty
                if (! isset($record["BoatVariant"])) {
                    $boat_records = $this->socket->find_records_sorted_matched("efa2boats", 
                            ["Id" => $record["BoatId"]
                            ], 1, "=", "InvalidFrom", false);
                    if ($boat_records !== false)
                        $boat_variants = (isset($boat_records[0]["DefaultVariant"])) ? $boat_records[0]["DefaultVariant"] : 1;
                }
                // only accept changes for closed sessions, open // close to be done in efaWeb
                if (($mode > 1) && isset($record["Open"]) && ((strcasecmp($record["Open"], "on") == 0) ||
                         (strcasecmp($record["Open"], "true") == 0)))
                    return "Offene Fahrten dürfen nur in efaWeb oder im PC-Programm bearbeitet werden.";
            }
        } elseif (strcasecmp($tablename, "efa2clubworkbook") == 0) {
            // Step 1: make sure both start and end date are within logbook range
            include_once "../classes/efa_config.php";
            $efa_config = new Efa_config($this->toolbox);
            $efa_config->load_efa_config();
            $clubworkbook_name = $record["Clubworkbookname"];
            $clubworkbook_period = $efa_config->get_book_period($clubworkbook_name, false);
            if ($clubworkbook_period["book_matched"] === false)
                return "Das Vereinsarbeitsbuch $clubworkbook_name ist nicht bekannt.";
            $clubworkbook_start = $clubworkbook_period["start_time"];
            $clubworkbook_end = $clubworkbook_period["end_time"];
            $work_date = strtotime($record["Date"]);
            $error_message = "Die Vereinsarbeit am " . date("d.m.Y", $work_date) .
                     " liegt nicht im gültigen Zeitraum für das Vereinsarbeitsbuch '$clubworkbook_name' (" .
                     date("d.m.Y", $clubworkbook_start) . " - " . date("d.m.Y", $clubworkbook_end) . ")";
            if (($work_date < $clubworkbook_start) || ($work_date > $clubworkbook_end))
                return $error_message;
        } elseif ($api_version >= 3) {
            // the following tables are only checked for API level 3 and higher
            if (strcasecmp($tablename, "efa2boatreservations") == 0) {
                $has_overlap_reservation = $this->has_overlap_reservation($record);
                if ($has_overlap_reservation)
                    return $has_overlap_reservation;
                if (strtotime($record["DateFrom"]) < time())
                    return "Das Beginn-Datum muss in der Zukunft liegen.";
                if (strtotime($record["DateFrom"]) > strtotime($record["DateTo"]))
                    return "Das Beginn-Datum muss vor oder an dem Ende Datum liegen.";
                if (! isset($record["PersonId"]))
                    return "Nur Personen, die im Fahrtenbuch hinterlegt sind, können ein Boot reservieren.";
                if (is_numeric($record["TimeFrom"]))
                    $record["TimeFrom"] = $record["TimeFrom"] . ":00";
                elseif (! is_numeric(explode(":", $record["TimeFrom"])[0]))
                    $record["TimeFrom"] = "00:00";
                if (is_numeric($record["TimeTo"]))
                    $record["TimeFrom"] = $record["TimeTo"] . ":00";
                elseif (! is_numeric(explode(":", $record["TimeTo"])[0]))
                    $record["TimeTo"] = "23:59";
            } elseif (strcasecmp($tablename, "efa2project") == 0) {
                // Projects shall not be edited in efaCloud
                return "Projektdatensätze dürfen in efaCloud nicht bearbeitet werden. Bitte verwende dafür efa.";
            } elseif (strcasecmp($tablename, "efa2sessiongroups") == 0) {
                $session_start = strtotime($record["StartDate"]);
                $session_end = strtotime($record["EndDate"]);
                $session_duration = ($session_end - $session_start) / 86400 + 1;
                if (isset($record["ActiveDays"]) && ((intval($record["ActiveDays"]) > $session_duration) ||
                         (intval($record["ActiveDays"]) < 1)))
                    return "Das Feld 'ActiveDays' hat einen ungültigen Wert.";
                $session_logbook_records = $this->socket->find_records_sorted_matched("efa2logbook", 
                        ["SessionGroupId" => $record["Id"]
                        ], 1000, "=", "Date", true);
                foreach ($session_logbook_records as $session_logbook_record) {
                    $entry_start = strtotime($session_logbook_record["Date"]);
                    if (($entry_start < $session_start) || ($entry_start > $session_end))
                        return "Das Datum des Fahrtenbucheintrags " . $session_logbook_record["EntryId"] .
                                 " liegt außerhalb des Zeitraums, " .
                                 "der für die ausgewählte Fahrtgruppe '{name}' angegeben wurde.";
                    if (isset($record["EndDate"]) && (strlen($record["EndDate"]) > 0)) {
                        $entry_end = strtotime($session_logbook_record["EndDate"]);
                        if (($entry_end < $session_start) || ($entry_end > $session_end))
                            return "Das Endedatum des Fahrtenbucheintrags " .
                                     $session_logbook_record["EntryId"] . " liegt außerhalb des Zeitraums, " .
                                     "der für die ausgewählte Fahrtgruppe '{name}' angegeben wurde.";
                    }
                }
            } elseif (strcasecmp($tablename, "efa2statistics") == 0) {
                // check whether "Meldedaten" have become public.
                if ((strlen($record["PubliclyAvailable"]) > 0) &&
                         (strcasecmp($record["OutputType"], "EfaWett") == 0))
                    return "Das Erstellen von Meldedateien in öffentliche Statistiken ist nicht erlaubt.";
            } elseif (strcasecmp($tablename, "efa2status") == 0) {
                // make sure guests and other are never counted as members
                if ((strcasecmp($record["Type"], "GUEST") == 0) || (strcasecmp($record["Type"], "OTHER") == 0))
                    $record["Membership"] = 0;
            }
        }
        return $record;
    }

    /**
     * Remove all fields from the record and create a "deleted record" to memorize deletion. In order to work,
     * the record must contain all its data fields. The following fields are NOT deleted:
     * Efa_tables::$efa_data_key_fields, 'ecrid', 'InvalidFrom', self::$assert_not_empty_fields,
     * 'ChangeCount', 'LastModified'.
     * 
     * @param array $tablename
     *            the table out of which the record shall be deleted.
     * @param array $record
     *            record which shall be deleted.
     * @return the record containing all fields for the subsequent update command or false, if the record
     *         needs no clearing for delete (usual reason: was already cleared).
     */
    public static function clear_record_for_delete (String $tablename, array $record)
    {
        // create a copy and delete all information which can be deleted
        $record_emptied = $record;
        $changes_needed = false;
        
        foreach ($record as $key => $value) {
            if (in_array($key, Efa_tables::$efa_data_key_fields[$tablename]) ||
                     (strcasecmp($key, "ecrid") == 0) || (strcasecmp($key, "InvalidFrom") == 0) ||
                     in_array($key, self::$assert_not_empty_fields[$tablename]) ||
                     (strcasecmp($key, "ChangeCount") == 0) || (strcasecmp($key, "LastModified") == 0)) {
                // do nothing, keep relevant data key fields
            } elseif ((strcasecmp($key, "LastModification") == 0) && (strcasecmp($value, "delete") != 0)) {
                // change modification to delete and register needed change.
                $record_emptied[$key] = "delete";
                $changes_needed = true;
            } elseif ((strcasecmp($key, "ecrhis") == 0) && (strlen($value) > 0)) {
                // remove the history instead of continuing it, when the socket executes the modification.
                $record_emptied[$key] = "REMOVE!";
                $changes_needed = true;
            } else {
                // Clear all other values
                if (in_array($key, Efa_tables::$int_fields[$tablename])) {
                    $record_emptied[$key] = 0; // integer values must not be ""
                    $changes_needed = $changes_needed || (intval($record[$key]) != 0);
                } elseif (strlen($value) > 0) {
                    $record_emptied[$key] = "";
                    $changes_needed = true;
                }
            }
        }
        
        if (! $changes_needed)
            return false;
        return $record_emptied;
    }

    /**
     * Validate a record based on a modification request provided by the efa-client (API version 1 or 2). All
     * change management and versioning support is run in the client, so only add the efaCloud specific fields
     * and trigger key change, if needed.
     * 
     * @param String $tablename
     *            The table to which the record belongs
     * @param array $record
     *            Record to be checked
     * @param int $mode
     *            Set 1 for insert, 2 for update
     * @param int $efaCloudUserID
     *            the clients userID to be used for the ClientSideKey entry
     * @return array|String array with [ 0 => the $record with possibly the modified key and ClientSideKey
     *         field, 1 => if the key was modified true, else false ] or an error message.
     */
    public function validate_record_APIv1v2 (String $tablename, array $record, int $mode, int $efaCloudUserID)
    {
        // API < version 3: There must not be an ecrid value
        $record_key = Efa_tables::get_record_key($tablename, $record);
        if (isset($record_key["ecrid"]))
            return "Cannot insert record, ecrid provisioning forbidden at API version below 3. ";
        // API < version 3: There must be a complete data key to insert, because if not, the record can
        // not be identified afterwards by the client. All other checks except keyfixing are done by the
        // client.
        $efa_data_key = Efa_tables::get_data_key($tablename, $record);
        if ($efa_data_key === false)
            return "Cannot insert record, efa data key is incomplete or missing. ";
        // always use the data key, when dealing with API level V1 and V2.
        $record_matched = $this->socket->find_record_matched($tablename, $efa_data_key);
        $key_was_modified = false;
        if ($mode == 1) { // insert
            if ($record_matched !== false) {
                // 1. reject insertion, if no key fixing is allowed
                if (! array_key_exists($tablename, Efa_tables::$efa_autoincrement_fields))
                    return "Cannot insert record. Provided key [" . json_encode($efa_data_key) .
                             "] is already in use and must not be fixed.";
                // 2. reject insertion on age, even if key fixing would be allowed.
                $last_modified_secs = intval(
                        mb_substr($record_matched["LastModified"], 0, 
                                mb_strlen($record_matched["LastModified"]) - 3));
                if ((time() - $last_modified_secs) > (30 * 24 * 3600))
                    // If the record was not touched for more than 30 days, reject any insertion with an
                    // already used key. Only updates are possible. (see also java constant
                    // de.nmichael.efa.data.efacloud.SynchControl.synch_upload_look_back_ms in efa.)
                    return "Cannot insert record. Is already over 30 days old (" .
                             date("Y-m-d", $last_modified_secs) . ") and the provided key [" .
                             json_encode($efa_data_key) . "] is already in use.";
                // 3. Prepare key fixing. Copy the client side key to the ClientSideKey field using the
                // provided $efa_data_key. From 2.3.2_09 onwards this is no more done for messages
                if ((strcasecmp($tablename, "efa2messages") != 0)) {
                    $record["ClientSideKey"] = $efaCloudUserID . ":" .
                             $this->compile_clientSideKey($tablename, $efa_data_key);
                    // autoincrement the numeric part of the key. Note that for the logbook tables that will
                    // need the logbook name, because all logbooks are in one single table at the server
                    // side.
                    $autoincrement_field = Efa_tables::$efa_autoincrement_fields[$tablename];
                    $record[$autoincrement_field] = Efa_tables::autoincrement_key_field($tablename, 
                            (isset($record["Logbookname"]) ? $record["Logbookname"] : ""), $this->socket);
                }
                // remember change. From 2.3.2_09 onwards this is used in the efa2messages table to convert
                // insert to update later.
                $key_was_modified = true;
            } else {
                // no record with the given data key exists, it can be safely inserted.
            }
        } elseif ($mode == 2) { // update
            if ($record_matched === false) {
                return "Cannot update record. No record found for key [" . json_encode($efa_data_key) .
                         "] in $tablename.";
            } else {
                $record["ecrid"] = $record_matched["ecrid"];
            }
        } elseif ($mode == 3) { // delete: only add ecrid for subsequent record identification and return
            if ($record_matched === false) {
                return "Cannot delete record. No record found for key [" . json_encode($efa_data_key) .
                         "] in $tablename.";
            } else {
                $record["ecrid"] = $record_matched["ecrid"];
                return [$record,false
                ];
            }
        }
        if ($mode != 3) {
            $record = $this->check_efa_semantic_validity($tablename, $record, $mode, 2);
            if (! is_array($record))
                return "Cannot modify record. Period violation in $tablename. " . $record;
        }
        
        // Add efaCloud specific system generated fields.
        $record = Efa_tables::add_system_fields_APIv1v2($record, $tablename, $mode, $efaCloudUserID);
        // Add or update virtual fields
        $record_plus_vf = Efa_tables::add_virtual_fields($record, $tablename, $this->toolbox, $this->socket);
        if ($record_plus_vf !== false)
            $record = $record_plus_vf;
        
        return [$record,$key_was_modified
        ];
    }

    /**
     * Validate record and replace names by Ids. Ensure existance, uniqueness, look-up and syntactical
     * validity. For update: Ensure uniqueness of first/last name, validity of status, gender, and email, if
     * provided. StatusName will then be mapped to StatusId, ValidFromDate to ValidFrom and InvalidFromDate to
     * InvalidFrom. StatusName, ValidFromDate and InvalidFromDate will be unset. Executes
     * Efa_tables::add_system_fields_APIv3 and Efa_tables::add_virtual_fields on the record before returning.
     * 
     * @param String $tablename
     *            the record's table name
     * @param array $record
     *            the record to insert as new or update as existing
     * @param int $mode
     *            Set 1 for insert, 2 for update
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field in insert mode.
     * @return array|String the validated and possibly adjusted record or an error message for user display on
     *         all errors.
     */
    private function validate_non_versionized (String $tablename, array $record, int $mode, 
            int $efaCloudUserID)
    {
        if (($mode > 1) && ! isset($record["ecrid"]))
            return "Ecrid missing. No change or delete of records without ecrid in API V3 and above.";
        // no checks for delete
        // TODO ADD REFERENCE INTEGRITY CHECKS
        if ($mode == 3)
            return $record;
        
        // Map extra fields and map and check boolean and integer syntax
        $record = $this->map_and_remove_extra_name_fields($record, $tablename);
        $record = $this->map_and_check_bool_fields($record, $tablename);
        if (! is_array($record))
            return $record;
        $record = $this->check_int_fields($record, $tablename);
        if (! is_array($record))
            return $record;
        
        // auto-incremental IDs must be provided by the server for data base integrity reasons
        $is_autoincrement = (array_key_exists($tablename, Efa_tables::$efa_autoincrement_fields));
        $is_set_autoincrement_value = $is_autoincrement &&
                 isset($record[Efa_tables::$efa_autoincrement_fields[$tablename]]);
        if (($mode == 1) && $is_autoincrement && $is_set_autoincrement_value)
            return "The autoincremented field " . Efa_tables::$efa_autoincrement_fields[$tablename] .
                     " must not be set within a record at API V3";
        
        // add all system generated fields. Statistic IDs such as UUID and ecrid may be
        // provided by the API client and will not be overwritten.
        $record = Efa_tables::add_system_fields_APIv3($record, $tablename, $mode, $efaCloudUserID, 
                $this->socket);
        $record_plus_vf = Efa_tables::add_virtual_fields($record, $tablename, $this->toolbox, $this->socket);
        if ($record_plus_vf !== false)
            $record = $record_plus_vf;
        
        // check data uniqueness and completeness
        $data_completeness = $this->check_unique_and_not_empty($record, $tablename, $mode);
        if (strlen($data_completeness) > 0)
            return $data_completeness;
        
        // check data correctness. This is only called from API V3 validation
        $record = $this->check_efa_semantic_validity($tablename, $record, $mode, 3);
        // This was the last check, return the result anyway.
        return $record;
    }

    /**
     * Validate a version of a versionized record and replace names by Ids. Ensure existance, uniqueness,
     * look-up and syntactical validity. For update: Ensure uniqueness of first/last name, validity of status,
     * gender, and email, if provided. StatusName will then be mapped to StatusId, ValidFromDate to ValidFrom
     * and InvalidFromDate to InvalidFrom. StatusName, ValidFromDate and InvalidFromDate will be unset.
     * Executes Efa_tables::add_system_fields_APIv3 and Efa_tables::add_virtual_fields on the record before
     * returning.
     * 
     * @param String $tablename
     *            The tablename of the record. UUID resolution ist performed using the respective list within
     *            the set "../config/lists/efaAuditUUIDnames" which helps to repeatedly identify the most
     *            recent record version.
     * @param array $version_record
     *            the record to insert as new, update as existing or insert as new version
     * @param int $mode
     *            Set 1 for insert, 2 for update.
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field in insert mode.
     * @param bool $force_refresh
     *            set true to force an index refresh, even if the index was already build.
     * @return array|String the validated and possibly adjusted record or an error message for user display on
     *         all errors.
     */
    private function validate_versionized (String $tablename, array $version_record, int $mode, 
            int $efaCloudUserID, bool $force_refresh)
    {
        $list_id = self::$uuid_list_id[$tablename];
        $this->build_indices($list_id, false);
        
        // Check whether the object in question exists. Try ecrid and UUID, if given, or resolve the name else
        $version_record = $this->check_against_existing($list_id, $version_record, $mode, $force_refresh);
        if (! is_array($version_record))
            return $version_record;
        // no more checks for deletion yet
        // TODO ADD REFERENCE INTEGRITY CHECKS
        if ($mode == 3)
            return $version_record;
        
        // Map extra fields and map and check boolean and integer syntax
        $version_record = $this->map_and_remove_extra_name_fields($version_record, $tablename);
        $version_record = $this->map_and_check_bool_fields($version_record, $tablename);
        if (! is_array($version_record))
            return $version_record;
        $version_record = $this->check_int_fields($version_record, $tablename);
        if (! is_array($version_record))
            return $version_record;
        
        // Check version validity
        if (($mode == 2) && isset($version_record["ValidFrom"]) && (strlen($version_record["ValidFrom"]) > 0)) {
            return "Die Angabe eines Gültigkeitsstarts ist für Änderungen nicht zulässig.";
        }
        if ($mode == 1) {
            if (isset($version_record["InvalidFrom"]) && (strlen($version_record["InvalidFrom"]) > 0) &&
                     (strcmp($version_record["InvalidFrom"], Efa_tables::$forever64) != 0))
                return "Für neue Objekte darf die Gültigkeit nicht begrenzt werden.";
            // add the ValidFrom and InvalidFrom timestamps.
            if (! isset($version_record["ValidFrom"]) || (strlen($version_record["ValidFrom"]) == 0))
                $version_record["ValidFrom"] = time() . "000";
            $version_record["InvalidFrom"] = Efa_tables::$forever64;
        }
        
        // add all system generated fields. Statistic IDs such as UUID and ecrid may be
        // provided by the API client and will not be overwritten.
        $version_record = Efa_tables::add_system_fields_APIv3($version_record, $tablename, $mode, 
                $efaCloudUserID, $this->socket);
        $version_record_plus_vf = Efa_tables::add_virtual_fields($version_record, $tablename, $this->toolbox, 
                $this->socket);
        if ($version_record_plus_vf !== false)
            $version_record = $version_record_plus_vf;
        
        // check data uniqueness and completeness
        $data_completeness = $this->check_unique_and_not_empty($version_record, $tablename, $mode);
        if (strlen($data_completeness) > 0)
            return $data_completeness;
        
        // check data correctness
        if (isset($version_record["Gender"]) && (strcasecmp($version_record["Gender"], "MALE") != 0) &&
                 (strcasecmp($version_record["Gender"], "FEMALE") != 0))
            return "Das Geschlecht muss entweder 'MALE' oder 'FEMALE' sein.";
        if (isset($version_record["Email"]) && (strlen($version_record["Email"]) > 0)) {
            if (filter_var($version_record["Email"], FILTER_VALIDATE_EMAIL) === false)
                return "Die Angabe " . $version_record["Email"] . " stellt keine gültige E-Mail-Adresse dar.";
        }
        if (strcasecmp($tablename, "efa2destinations") == 0) {
            // TODO: "eigener Zielbereich". Specific for "FunctionalityRowingBerlin", they have those areas
            // and must not create new.
        }
        
        // all checks completed, return record.
        return $version_record;
    }

    /**
     * Validate record and replace names by Ids. Ensure existance, uniqueness, look-up and syntactical
     * validity. For update: Ensure uniqueness of first/last name, validity of status, gender, and email, if
     * provided. StatusName will then be mapped to StatusId, ValidFromDate to ValidFrom and InvalidFromDate to
     * InvalidFrom. StatusName, ValidFromDate and InvalidFromDate will be unset. Executes
     * Efa_tables::add_system_fields_APIv3 and Efa_tables::add_virtual_fields on the record before returning.
     * 
     * @param String $tablename
     *            the record's table name
     * @param array $record
     *            the record to insert as new or update as existing
     * @param int $mode
     *            Set 1 for insert, 2 for update
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field in insert mode.
     * @param bool $force_refresh
     *            set true to force an index refresh for versionized check, even if the index was already
     *            build.
     * @return array|String the validated and possibly adjusted record or an error message for user display on
     *         all errors.
     */
    public function validate_record_APIv3 (String $tablename, array $record, int $mode, int $efaCloudUserID, 
            bool $force_refresh)
    {
        if (in_array($tablename, Efa_tables::$versionized_table_names))
            $record = $this->validate_versionized($tablename, $record, $mode, $efaCloudUserID, $force_refresh);
        else
            $record = $this->validate_non_versionized($tablename, $record, $mode, $efaCloudUserID);
        return $record;
    }

    /**
     * Return the 'client side key' value for a record which will then be used to be stored in a ClientSideKey
     * field or compared to such a field's value. It is a concatenation of all efa data key fields as defined
     * in Efa_tables::$efa_data_key_fields[], separated by a "|". The key fields are the same in the server as
     * in the client EXCEPT FOR THE LOGBOOK. Within the client each year has a separate logbook with EntryIds
     * starting from 1 at the first of January. The server uses just one table with an additional data field
     * Logbookname. The client side key is just the EntryId without the Logbookname.
     * 
     * @param String $tablename
     *            name of table to find the record
     * @param array $record
     *            record to find
     * @return mixed the key as value for the ClientSideKey comparison, if for all key fields a value is
     *         provided, else false.
     */
    public static function compile_clientSideKey (String $tablename, array $record)
    {
        $matching = Efa_tables::get_data_key($tablename, $record);
        if (! $matching || (count($matching) == 0))
            return false;
        if (strcasecmp($tablename, "efa2logbook") == 0)
            return $record["EntryId"];
        $values = "";
        foreach ($matching as $key => $value)
            $values .= $value . "|";
        return mb_substr($values, 0, mb_strlen($values) - 1);
    }

    /**
     * Get all reservations for the boat with the $reservation_record["BoatId"] value and check whether any
     * overlap.
     * 
     * @param array $reservation_record
     *            the reservation record to check
     * @return false in case of no overlap, else a String representation of the overlapping reservation.
     */
    private function has_overlap_reservation (array $reservation_record)
    {
        $all_reservations = $this->socket->find_records_sorted_matched("efa2boatreservations", 
                ["BoatId" => $reservation_record["BoatId"]
                ], 1000, "=", "DateFrom", false);
        if ($all_reservations === false)
            return false;
        if (strcasecmp($reservation_record["Type"], "WEEKLY") == 0) {
            $time_from_a = strtotime($reservation_record["DateTo"]);
            $time_to_a = strtotime($reservation_record["TimeTo"]);
        } else {
            $time_from_a = strtotime($reservation_record["DateFrom"] . " " . $reservation_record["TimeFrom"]);
            $time_to_a = strtotime($reservation_record["DateTo"] . " " . $reservation_record["TimeTo"]);
        }
        $is_weekly_record = (strcasecmp($reservation_record["Type"], "WEEKLY") == 0);
        foreach ($all_reservations as $reservation_to_check) {
            $is_this_reservation = (strcmp($reservation_to_check["ecrid"], $reservation_record["ecrid"]) == 0);
            $is_weekly_check = (strcasecmp($reservation_to_check["Type"], "WEEKLY") == 0);
            // must be different from the one provided, but must be of same type (see efa code for reference
            // of condiition
            if (! $is_this_reservation) {
                if ($is_weekly_record == $is_weekly_check) {
                    if ($is_weekly_record) {
                        if (strcasecmp($reservation_to_check["DayOfWeek"], $reservation_record["DayOfWeek"]) ==
                                 0) {
                            // compare weekly reservations, use time only
                            $time_from_b = strtotime($reservation_to_check["TimeFrom"]);
                            $time_to_b = strtotime($reservation_to_check["TimeTo"]);
                        } else {
                            // different days of the week, invalidate time for further check
                            $time_from_b = - 1;
                            $time_to_b = - 1;
                        }
                    } else {
                        // compare one-time reservations
                        $time_from_b = strtotime(
                                $reservation_to_check["DateFrom"] . " " . $reservation_to_check["TimeFrom"]);
                        $time_to_b = strtotime(
                                $reservation_to_check["DateTo"] . " " . $reservation_to_check["TimeTo"]);
                    }
                    $a_starts_within_b = ($time_from_a > $time_from_b) && ($time_from_a < $time_to_b);
                    $a_ends_within_b = ($time_to_a > $time_from_b) && ($time_to_a < $time_to_b);
                    $a_includes_b = ($time_from_a <= $time_from_b) && ($time_to_a >= $time_to_b);
                    if ($a_starts_within_b || $a_includes_b || $a_ends_within_b)
                        return "Das Boot ist belegt mit der Buchung: " . $reservation_to_check["Reason"] .
                                 " vom " . $reservation_to_check["DateFrom"] . " " .
                                 $reservation_to_check["TimeFrom"] . " bis " . $reservation_to_check["DateTo"] .
                                 " " . $reservation_to_check["TimeTo"];
                }
            }
        }
        return false;
    }

    /**
     * Update the efa2autoincrement counter based on the given record. Shall be executed immediately after
     * record insertion of an autoincremented table. This will not return any result. It is no integral part
     * of the modify_record function, because for API V1 / V2 it will be separately triggered by the client.
     * 
     * @param String $tablename
     *            the table of which the autoincrement counter shall be incremented
     * @param array $record
     *            the record with the new autoincrement maximum value.
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field in insert mode.
     */
    public function update_efa2autoincrement (String $tablename, array $record, int $efaCloudUserID)
    {
        if (! array_key_exists($tablename, Efa_tables::$efa_autoincrement_fields))
            return;
        $numeric_id = intval($record[Efa_tables::$efa_autoincrement_fields[$tablename]]);
        $matching_key = ["Sequence" => $tablename
        ];
        $autoincrement_record = $this->socket->find_record_matched("efa2autoincrement", $matching_key);
        if ($autoincrement_record !== false) { // no such record for efa2logbook
            if (strcasecmp($tablename, "efa2messages") == 0)
                $autoincrement_record["LongValue"] = $numeric_id;
            else
                $autoincrement_record["IntValue"] = $numeric_id;
            Efa_tables::register_modification($autoincrement_record, time(), 
                    $autoincrement_record["ChangeCount"], "update");
            $this->socket->update_record_matched($efaCloudUserID, "efa2autoincrement", $matching_key, 
                    $autoincrement_record);
        }
    }

    /**
     * Execute the modification after record or object version validation according to the API level. This
     * execution has no logic except error reporting.
     * 
     * @param String $tablename
     *            the record's table name
     * @param array $record
     *            the record to insert as new or update as existing
     * @param int $mode
     *            Set 1 for insert, 2 for update, 3 for delete
     * @param int $efaCloudUserID
     *            The user ID which is put to the ecrown field in insert mode.
     * @param bool $allow_delete
     *            Set to true to allow deletion in tables for which this is normally forbidden. THis is
     *            needed, because the efa-PC client sometimes double records and deletes one thereafter.
     *            Default is false.
     * @return String|int an error message for user display on all modification errors, else an empty String
     *         for $mode < 3. For $mode == 3, a numeric result code is returned: 0 for ok, 1 in case nothing
     *         had to be done, because the record is already deleted, 2 in case of any trash insertion error.
     */
    public function modify_record (String $tablename, array $record, int $mode, int $efaCloudUserID, 
            bool $allow_delete)
    {
        $error_text = (in_array($tablename, Efa_tables::$versionized_table_names)) ? "Die Version des Objekts " .
                 $record["Id"] : "Der Datensatz ";
        if ($mode == 1) {
            // insert record
            $insert_result = $this->socket->insert_into($efaCloudUserID, $tablename, $record);
            if (! is_numeric($insert_result))
                return "Datenbankfehler. " . $error_text . " konnte in $tablename nicht hinzugefügt werden: " .
                         $insert_result;
            return "";
        } elseif ($mode == 2) {
            // update record
            $matching_key = ["ecrid" => $record["ecrid"]
            ]; // at the efaCloud server all records have an ecrid, regardless of the API-level used.
            $update_result = $this->socket->update_record_matched($efaCloudUserID, $tablename, $matching_key, 
                    $record);
            if (strlen($update_result) > 0)
                return "Datenbankfehler. " . $error_text . " konnte in $tablename nicht aktualisiert werden: " .
                         $update_result;
            return "";
        } elseif ($mode == 3) {
            // delete record and create a trash entry
            if (in_array($tablename, self::$allow_web_delete) || $allow_delete) {
                $record_key = ["ecrid" => $record["ecrid"]
                ]; // at the efaCloud server all records have an ecrid, regardless of the API-level used.
                $record_to_delete = $this->socket->find_record_matched($tablename, $record_key);
                if ($record_to_delete === false)
                    return "Datenbankfehler. " . $error_text .
                             " konnte aus $tablename nicht gelöscht werden: nicht vorhanden.";
                // Empty the record rather than delete it. This is to ensure that the deletion can be
                // propagated to other clients.
                $record_emptied = self::clear_record_for_delete($tablename, $record_to_delete);
                // and update the record with the deletion stub, if needed
                if ($record_emptied != false) {
                    $record_emptied = Efa_tables::register_modification($record_emptied, time(), 
                            $record_emptied["ChangeCount"], "delete");
                    $update_result = $this->socket->update_record_matched($efaCloudUserID, $tablename, 
                            $record_key, $record_emptied);
                    if (strlen($update_result) > 0)
                        return "Datenbankfehler. " . $error_text .
                                 " aus $tablename konnte nicht inhaltlich geleert werden: " . $update_result;
                    else {
                        $trashed_record = json_encode($record_to_delete);
                        // limit size to 64k
                        $cut_len = 65535 - 4096;
                        while (strlen($trashed_record) > 65535) { // strlen == byte length
                            foreach ($record_to_delete as $key => $value)
                                if (strlen($value) > $cut_len)
                                    $record_to_delete[$key] = substr(strval($record_to_delete[$key]), 0, 
                                            $cut_len);
                            $trashed_record = json_encode($record_to_delete);
                            $cut_len = $cut_len - 4096;
                        }
                        $trashRecord = ["Table" => $tablename,"TrashedRecord" => $trashed_record
                        ]; // ID and TrashedAt will be added by the data base.
                        $trashing_result = $this->socket->insert_into($efaCloudUserID, "efaCloudTrash", 
                                $trashRecord);
                        return (is_numeric($trashing_result)) ? 0 : 2;
                    }
                } else
                    return 1;
            } else {
                return "Datensätze der Tabelle $tablename dürfen nicht gelöscht werden.";
            }
        }
    }
}