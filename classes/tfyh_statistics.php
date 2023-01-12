<?php

/**
 * Container to hold the audit class. Shall be run by the cron jobs.
 */
class Tfyh_statistics
{

    /**
     * The pivoted array of timestamps
     */
    public $timestamps_pivot;

    /**
     * last timestamp er client
     */
    public $timestamps_last;

    /**
     * last timestamp er client
     */
    public $timestamps_count;

    /**
     * empty Constructor.
     */
    public function __construct ()
    {}

    /**
     * Create an html readable summary of the application status to send it per mail to admins.
     */
    public function create_app_status_summary (Tfyh_toolbox $toolbox, Tfyh_socket $socket)
    {
        // Check logbooks
        $total_record_count = 0;
        // check table sizes
        $html = "<h4>Tabellen und Datensätze</h4>\n";
        $html .= "<table><tr><th>Tabelle</th><th>Anzahl Datensätze</th></tr>\n";
        $table_names = $socket->get_table_names();
        $total_record_count = 0;
        foreach ($table_names as $tn) {
            $record_count = $socket->count_records($tn);
            $html .= "<tr><td>" . $tn . "</td><td>" . $record_count . "</td></tr>\n";
            $total_record_count += $record_count;
        }
        $html .= "<tr><td>Summe</td><td>" . $total_record_count . "</td></tr></table>\n";
        
        // Check users and access rights
        $html .= $toolbox->users->get_all_accesses($socket, false);
        
        // Check accessses logged.
        $days_to_log = 14;
        $html .= "<h4>Zugriffe letzte " . $days_to_log . " Tage</h4>\n";
        include_once '../classes/tfyh_statistics.php';
        $tfyh_statistics = new Tfyh_statistics();
        file_put_contents("../log/efacloud_server_statistics.csv", 
                $tfyh_statistics->pivot_timestamps(86400, $days_to_log));
        $html .= "<table><tr><th>clientID</th><th>clientName</th><th>Anzahl Zugriffe</th></tr>\n";
        $timestamps_count_all = 0;
        foreach ($tfyh_statistics->timestamps_count as $clientID => $timestamps_count) {
            $user = (intval($clientID) === -1) ? "Anonym" : ((intval($clientID) === 0) ? "undefiniert" : "Nutzer");
            $html .= "<tr><td>" . $clientID . "</td><td>" . $user . "</td><td>" .
                     $timestamps_count . "</td></tr>\n";
            $timestamps_count_all += $timestamps_count;
        }
        $html .= "<tr><td>Summe</td><td></td><td>" . $timestamps_count_all . "</td></tr></table>\n";
        
        // Check backup
        $html .= "<h4>Backups</h4>\n";
        $backup_dir = "../log/backup";
        $backup_files = scandir($backup_dir);
        $backup_files_size = 0;
        $backup_files_count = 0;
        $backup_files_youngest = 0;
        foreach ($backup_files as $backup_file) {
            if (strcasecmp(substr($backup_file, 0, 1), ".") != 0) {
                $backup_files_size += filesize($backup_dir . "/" . $backup_file);
                $lastmodified = filectime($backup_dir . "/" . $backup_file);
                if ($lastmodified > $backup_files_youngest)
                    $backup_files_youngest = $lastmodified;
                $backup_files_count ++;
            }
        }
        $html .= "<p>" . $backup_files_count . " Backup-Archive mit in Summe " .
                 (intval($backup_files_size / 1024 / 102) / 10) . " MByte. \n";
        $html .= "Jüngstes Backup von " . date("Y-m-d H:i:s", $backup_files_youngest) . ".</p>\n";
        
        return $html;
    }

    /**
     * Pivot the timestamps according to the pivoting period.
     *
     * @param int $period
     *            pivoting period in seconds
     * @param int $count
     *            count of periods to pivot
     */
    public function pivot_timestamps (int $period, int $count)
    {
        $timestamps_file = file_get_contents("../log/sys_timestamps.log");
        $timestamps_file_wo_header = explode("\n", $timestamps_file, 2)[1];
        $timestamps_previous_file = file_get_contents("../log/sys_timestamps.log.previous");
        $timestamps_all = $timestamps_previous_file . "\n" . $timestamps_file_wo_header;
        $timestamps_lines = explode("\n", $timestamps_file);
        $timestamps_pivot = [];
        $this->timestamps_last = [];
        $this->timestamps_count = [];
        // end the monitoring iterval at the next full hour.
        $periods_end_at = strtotime(date("Y-m-d H") . ":00:00") + 3600;
        // and start it according to the period length and count requested.
        $periods_start_at = $periods_end_at - $count * $period;
        // Read timestamps file
        for ($l = 1; $l < count($timestamps_lines); $l ++) {
            // skip first line (header)
            $ts_parts = explode(";", trim($timestamps_lines[$l]));
            if (count($ts_parts) >= 4) {
                $ts_time = intval($ts_parts[0]);
                $ts_period_index = intval(($ts_time - $periods_start_at) / $period);
                if (($ts_period_index >= 0) && ($ts_period_index < $count)) {
                    $ts_user = intval($ts_parts[1]);
                    if (! isset($timestamps_pivot[$ts_user]))
                        $timestamps_pivot[$ts_user] = [];
                    if (! isset($this->timestamps_last[$ts_user]))
                        $this->timestamps_last[$ts_user] = 0;
                    if (! isset($this->timestamps_count[$ts_user]))
                        $this->timestamps_count[$ts_user] = 0;
                    $ts_period_start = $periods_start_at + $ts_period_index * $period;
                    // an api container may contain more than one transaction.
                    $ts_accesses = explode(",", $ts_parts[2]);
                    // use the average duration per transaction within the container for monitoring
                    $ts_duration = $ts_parts[3] / count($ts_accesses);
                    // pivot numbers
                    foreach ($ts_accesses as $ts_access) {
                        $this->timestamps_count[$ts_user] ++;
                        if ($ts_time > $this->timestamps_last[$ts_user])
                            $this->timestamps_last[$ts_user] = $ts_time;
                        $ts_access_group = explode("/", $ts_access)[0];
                        $ts_access_type = explode("/", $ts_access)[1];
                        // initialze pivot table structure
                        if (! isset($timestamps_pivot[$ts_user][$ts_access_group]))
                            $timestamps_pivot[$ts_user][$ts_access_group] = [];
                        if (! isset($timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type])) {
                            $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type] = [];
                            for ($i = 0; $i < $count; $i ++) {
                                $period_index = $periods_start_at + $period * $i;
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index] = [];
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index]["sum"] = 0.0;
                                $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$period_index]["count"] = 0;
                            }
                        }
                        // add timestamp
                        $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$ts_period_start]["sum"] += $ts_duration;
                        $timestamps_pivot[$ts_user][$ts_access_group][$ts_access_type][$ts_period_start]["count"] ++;
                    }
                }
            }
        }
        $this->timestamps_pivot = $timestamps_pivot;
        
        // format pivot
        $pivot_linear = "Group;Type;Period;Count;Sum (ms);Average (ms)\n";
        foreach ($timestamps_pivot as $ts_user => $pivot_user)
            foreach ($pivot_user as $ts_access_group => $pivot_access_group)
                foreach ($pivot_access_group as $ts_access_type => $pivot_access_type)
                    foreach ($pivot_access_type as $ts_access_period => $pivot_access_period) {
                        $pivot_linear .= $ts_access_group . ";" . $ts_access_type . ";" .
                                 date("Y-m-d H:i:s", $ts_access_period) . ";" .
                                 $pivot_access_period["count"] . ";" .
                                 intval($pivot_access_period["sum"] * 1000) . ";";
                        if ($pivot_access_period["count"] > 0)
                            $pivot_linear .= substr(
                                    strval(
                                            intval(
                                                    1000 * $pivot_access_period["sum"] /
                                                             $pivot_access_period["count"])), 0, 6);
                        else
                            $pivot_linear .= "0";
                        $pivot_linear .= "\n";
                    }
        
        return $pivot_linear;
    }
}
