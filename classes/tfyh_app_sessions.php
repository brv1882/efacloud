<?php

/**
 * Class to handle sessions from an application perspective, in particular managing concurrency and load
 * throttling from application perspective. The session file has three integer numbers: started at (Unix
 * timestamp, seconds); refreshed at (delta seconds after started at, seconds); user ID. Its name is the
 * session ID which is either the PHP session id or the app-session ID. app sessions are used for non-browser
 * access, usually by a program API. Both PHP and app sessions are monitored to limit the maximum number of
 * concurrent sessions.
 */
class Tfyh_app_sessions
{

    public $max_session_duration;

    public $max_session_keepalive;

    public $max_concurrent_sessions;

    private $toolbox;

    private $debug_on;

    private $debug_file = "../log/debug_sessions.log";

    private $sessions_dir = "../log/sessions/";

    /**
     * Caching parameters
     * 
     * @param array $init_settings
     *            The tfyh init settings of $toolbox->config
     * @param int $debug_level
     *            the debug level of $toolbox->config
     */
    public function __construct (Tfyh_toolbox $toolbox)
    {
        $init_settings = $toolbox->config->settings_tfyh["init"];
        $this->max_session_keepalive = (isset($init_settings["max_session_keepalive"])) ? $init_settings["max_session_keepalive"] : 43200;
        $this->max_session_duration = (isset($init_settings["max_session_duration"])) ? $init_settings["max_session_duration"] : 600;
        $this->max_concurrent_sessions = (isset($init_settings["max_concurrent_sessions"])) ? $init_settings["max_concurrent_sessions"] : 25;
        if (! file_exists("../log/sessions"))
            mkdir("../log/sessions");
        $this->debug_on = ($toolbox->config->debug_level > 0);
        $this->toolbox = $toolbox;
    }

    /**
     * Read a session file
     * 
     * @param String $session_id
     *            the session ID = filename
     * @return the session as associative array: started_at, refreshed_at, user_id or false on errors
     */
    private function read_session (String $session_id)
    {
        $session_file = $this->sessions_dir . $session_id;
        if (! file_exists($session_file)) {
            if ($this->debug_on)
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . ": No such session file: " . $session_file . "\n", FILE_APPEND);
            return false;
        }
        $times_and_user = file_get_contents($session_file);
        if ($times_and_user === false) {
            if ($this->debug_on)
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . "\n Failed to read session file: " . $session_file . "\n", 
                        FILE_APPEND);
            return false;
        }
        $parts = explode(";", $times_and_user);
        if (count($parts) < 3) {
            if ($this->debug_on)
                file_put_contents($this->debug_file, 
                        date("Y-m-d H:i:s") . "\n Malformatted session file: " . $session_file . "\n", 
                        FILE_APPEND);
            return false;
        } else {
            $session = array();
            $session["started_at"] = intval($parts[0]);
            $session["refreshed_at"] = intval($parts[1]);
            $session["user_id"] = intval($parts[2]);
            // part[3] is the transcription for readability, never used for technical purposes.
            return $session;
        }
    }

    /**
     * Cleanse the file system from expired sessions' files and count the remainder. If a session is cleansed,
     * this also closes the PHP session using $this->session_close().
     */
    private function cleanse_and_count_sessions ()
    {
        $session_files = scandir("../log/sessions");
        $open_sessions_count = 0;
        foreach ($session_files as $session_file) {
            if (substr($session_file, 0, 1) != ".") {
                $session = $this->read_session($session_file);
                if ($session === false)
                    unlink($this->sessions_dir . $session_file);
                else {
                    $now = time();
                    if ($this->debug_on)
                        file_put_contents($this->debug_file, 
                                date("Y-m-d H:i:s") . ": Session checked: " . $session_file . ", started: " .
                                         date("Y-m-d H:i:s", $session["started_at"]) . ", refreshed: " .
                                         date("Y-m-d H:i:s", $session["refreshed_at"]) . "\n", FILE_APPEND);
                    if ($session["refreshed_at"] < $now - $this->max_session_duration) {
                        $this->session_close(
                                "exceeded maximum session duration of " . ($this->max_session_duration / 3600) .
                                         " hours.", $session_file);
                    } elseif ($session["started_at"] < $now - $this->max_session_keepalive) {
                        $this->session_close(
                                "exceeded maximum inactive time of " . ($this->max_session_keepalive / 60) .
                                         " minutes.", $session_file);
                    } else
                        $open_sessions_count ++;
                }
            }
        }
        return $open_sessions_count;
    }

    /**
     * Create a new app session Id.
     */
    public function create_app_session_id ()
    {
        $session_id = "tfyh" . $this->toolbox->generate_token(26, true);
        return $session_id;
    }

    /**
     * Get the user for a session.
     * 
     * @param String $session_id
     *            the session to look at
     * @return number the id of the user, if a session was found, else false.
     */
    public function session_user_id (String $session_id)
    {
        $session = $this->read_session($session_id);
        if ($session === false)
            return false;
        else
            return $session["user_id"];
    }

    /**
     * Start a session from application perspective. This will create or refresh the application session file.
     * The user may change, in essence for the login scenario to keep the session, for a user who was not
     * identified before the login. The PHP session context can be used: set the $session_id to "" or leave it
     * away to rely on it. The PHP session context stores the user record within the $_SESSION["User"]
     * including the user ID during the web login process.
     * 
     * @param int $user_id
     *            the user ID. Set to -1 for anonymous users.
     * @param bool $use_php
     *            the session ID = filename. Set to "" or omit to use the PHP session management. You may
     *            generate a new app session ID with the tfyh_app_sessions->create_app_session_id() function
     * @return true if opened, false else.
     */
    public function session_open (int $user_id, String $session_id = "")
    {
        // get the PHP context, if requested.
        if (strlen($session_id) == 0) {
            session_start();
            $session_id = session_id();
            $user_id = (isset($_SESSION["User"])) ? $_SESSION["User"][$this->toolbox->users->user_id_field_name] : - 1;
        }
        
        $now = time();
        $session_file = $this->sessions_dir . $session_id;
        $open_sessions_count = $this->cleanse_and_count_sessions();
        
        // read the session, if after cleansing still existing
        $existing_session = $this->read_session($session_id);
        if ($existing_session == false) {
            if ($open_sessions_count < $this->max_concurrent_sessions) {
                $human_readable = "started " . date("Y-m-d H:i:s", $now) . ", not yet refreshed, for user " .
                         $user_id;
                $started_session = $now . ";" . $now . ";" . $user_id . ";" . $human_readable;
                // open the new session
                if (file_put_contents($session_file, $started_session) !== false) {
                    if ($this->debug_on)
                        file_put_contents($this->debug_file, 
                                date("Y-m-d H:i:s") . ": Started new session: " . $human_readable . "\n", 
                                FILE_APPEND);
                    return true;
                } else {
                    if ($this->debug_on)
                        file_put_contents($this->debug_file, 
                                date("Y-m-d H:i:s") . ": Failed to write new session file: " . $human_readable .
                                         "\n", FILE_APPEND);
                    return false;
                }
            } else {
                if ($this->debug_on)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": Refused to start new session for: " . $user_id .
                                     " because of currently " . $open_sessions_count . "open sessions.\n", 
                                    FILE_APPEND);
                return false;
            }
        } else {
            $started = $existing_session["started_at"];
            $refreshed = time();
            $human_readable = $session_id . ", started " . date("Y-m-d H:i:s", $started) . ", refreshed " .
                     date("Y-m-d H:i:s", $refreshed) . ", for user " . $user_id;
            $refreshed_session = $started . ";" . $refreshed . ";" . $user_id . ";" . $human_readable;
            // refresh existing session.
            if (file_put_contents($session_file, $refreshed_session) !== false) {
                if ($this->debug_on)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": Refreshed session: " . $human_readable . "\n", 
                            FILE_APPEND);
                return true;
            } else {
                $this->toolbox->logger->log(2, 0, 
                        "Failed to write refreshed session file: " . $human_readable);
                if ($this->debug_on)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": Failed to write refreshed session file: " .
                                     $human_readable . "\n", FILE_APPEND);
                return false;
            }
        }
    }

    /**
     * Close a session. This will close the PHP session, if existing. If an $app_session_id is prvided this
     * will also close the app session, i. e. unlink the session file and cleanse the sessions directory.
     * 
     * @param String $cause
     *            the cause why the session was closed. Used for logging.
     * @param String $app_session_id
     *            the app session ID = filename, default is "", i. e. no app session to be closed.
     */
    public function session_close (String $cause, String $app_session_id = "")
    {
        $because_of = (strlen($cause) > 0) ? ": " . $cause : "";
        // close the PHP session, if existing.
        if (strlen(session_id()) > 0) {
            $session_id = session_id();
            session_destroy();
            $_SESSION = array();
            file_put_contents($this->debug_file, 
                    date("Y-m-d H:i:s") . ": Closed PHP session '$session_id' $because_of \n", FILE_APPEND);
        }
        // close the app session, if an $app_session_id is provided.
        if (strlen($app_session_id) > 0) {
            $unlink_success = unlink($this->sessions_dir . $app_session_id);
            if (! $unlink_success)
                $this->toolbox->logger->log(2, 0, 
                        "Unable to remove inactive app session '$app_session_id' $because_of");
            if ($this->debug_on) {
                if ($unlink_success)
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") . ": Closed app session '$app_session_id' $because_of \n", 
                            FILE_APPEND);
                else
                    file_put_contents($this->debug_file, 
                            date("Y-m-d H:i:s") .
                                     ": Failed to remove session file '$app_session_id' $because_of \n", 
                                    FILE_APPEND);
            }
        }
    }
}
    