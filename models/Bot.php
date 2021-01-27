<?php

/**
 * Class AdminBot
 */
class AdminBot
{
    /**
     * @var string your bot token
     */
    private $BOT_TOKEN;

    /**
     * @var string ID of the channel or group where the bot will send messages
     */
    private $CHAT_ID;

    /**
     * @var string main directory
     */
    private $PATH;

    /**
     * @var string log directory
     */
    private $LOG_PATH = "logs/";

    /**
     * @var string general log filename
     */
    private $GENERAL_LOG_NAME = 'main.log';

    /**
     * @var string service filename
     */
    private $SERVICE_FILE = 'service.conf';

    /**
     * @var array object with sites
     */
    private $SITES;

    /**
     * @var bool TRUE for print messages in browser
     */
    private $DEBUG;

    /**
     * @var bool TRUE to allow notify about unavailable sites every time
     */
    private $DISABLE_MILTY_NOTIFY;

    /**
     * @var string with the time intervals (based on 24 hours) when the bot can send messages
     * @example [
     *          "7-12",
     *          "13-15",
     *          "16-18"
     *          ]
     * use only integer number
     */
    private $AVAILABLE_NOTIFY_HOURS;

    /**
     * @var string greeting message
     */
    private $START_MESSAGE = 'Hello! Time to start working';

    /**
     * @var string reminder message
     */
    private $REMINDER_OF_NOTIFY = 'While I could not write, something happened. Num messages: ';

    /**
     * @var string time format for log files
     */
    private $TIME_FORMAT = 'Y-m-d H:i:s';

    /**
     * check the availability of the url
     * @param $url string full site address
     * @return array with response data
     */
    public function check_url($url)
    {
        $curl = curl_init();
        /**
         * description of settings for curl_setopt
         * @CURLOPT_URL $url
         * @CURLOPT_HEADER TRUE to include headers in the output
         * @CURLOPT_FAILONERROR TRUE to a detailed report on failure if the received HTTP code is greater than or equal to 400
         * @CURLOPT_RETURNTRANSFER TRUE to return the transfer result as a string instead of directly outputting to the browser
         * @CURLOPT_FORBID_REUSE TRUE to force the connection to close
         * @CURLOPT_FRESH_CONNECT TRUE to force the use of a new connection instead of a cached one
         * @CURLOPT_SSL_VERIFYSTATUS TRUE to check the status of the certificate
         * @CURLOPT_SSL_VERIFYPEER FALSE to stop cURL from checking the host certificate.
         * @CURLOPT_CONNECTTIMEOUT INT the number of seconds to wait while trying to connect
         *
         * default not using
         * @CURLOPT_USERPWD STRING login and password used for connection, specified in the format "[username]: [password]"
         */
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FAILONERROR, true);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, true);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_FRESH_CONNECT, true);
        curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 3);
        //curl_setopt($curl, CURLOPT_USERPWD, '');
        $is_available = curl_exec($curl);
        $info_url = curl_getinfo($curl);
        curl_close($curl);

        /**
         * @var array
         *      "is_available" => bool is the site available at the moment
         *      "code" => integer returned code
         */
        return array(
            "is_available" => boolval($is_available),
            "code" => $info_url['http_code']);
    }

    /**
     * @return bool
     * @internal
     */
    private function _check_available_notify_time()
    {
        $current_time = date('H');
        foreach ($this->AVAILABLE_NOTIFY_HOURS as $time_interval) {
            $interval_edges = explode('-', $time_interval);
            if ((int)$current_time >= (int)$interval_edges[0] && (int)$current_time < (int)$interval_edges[1]) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param $node_data
     * @param bool $is_anew_work
     * @return string joined message
     * @internal
     */
    private function _join_output_message($node_data, $is_anew_work = false)
    {
        if ($node_data['status'] !== 'ERROR') {
            return join(' ', [$node_data['time'], $node_data['url'], 'now is available!']);
        } else {
            if ($is_anew_work) {
                return join(' ', [date($this->TIME_FORMAT), $node_data['url'], 'now is available! Broken', $node_data['time'], 'with code:', $node_data['code']]);
            } else {
                if ($node_data['code'] == 0) {
                    $curl_message = $this->_get_curl_message_error($node_data['url']);
                    if ($curl_message)
                        return join(' ', [date($this->TIME_FORMAT), $node_data['url'], 'CURL message:', $curl_message]);
                    return join(' ', [date($this->TIME_FORMAT), $node_data['url'], 'there is no domain with this name or it is not available!']);
                } else {
                    return join(' ', [$node_data['time'], $node_data['url'], 'is unavailable! Code:', $node_data['code']]);
                }
            }
        }
    }

    /**
     * @param $url
     * @return NULL|string
     * @internal
     */
    private function _get_curl_message_error($url)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_exec($curl);
        $error_message = '';
        if ($error_code = curl_errno($curl)) {
            $error_message = curl_strerror($error_code);
        }
        curl_close($curl);
        return $error_message;
    }

    /**
     * @param string $url
     * @param bool $is_available
     * @param integer $code
     * @return string
     * @internal
     */
    private function _create_log_node($url, $is_available, $code)
    {
        //$url_to_log = preg_replace('#^https?://#', '', $url);
        $time = date($this->TIME_FORMAT);

        if ($is_available)
            return "$time GOOD $code $url";
        else
            return "$time ERROR $code $url";

    }

    /**
     * @param string $node
     * @return array
     * @internal
     */
    private function _parse_log_node($node)
    {
        $node_parts_array = explode(' ', $node);
        return array(
            "time" => join(' ', array($node_parts_array[0], $node_parts_array[1])),
            "status" => $node_parts_array[2],
            "code" => $node_parts_array[3],
            "url" => $node_parts_array[4],
            "notify" => sizeof($node_parts_array) === 6 ? $node_parts_array[5] : '',
        );
    }

    /**
     * @param array $messages
     * @internal
     */
    private function _output_url_results($messages)
    {
        $output_context = '';
        if (!$this->DEBUG) {
            foreach ($messages as $message_string) {
                $message_parts = explode(' ', $message_string);
                $message_parts[2] = '' . $message_parts[2] . '';
                $output_context = join(' ', $message_parts);
                if ($this->_check_available_notify_time()) {
                    $this->send_message($output_context);
                }
                $this->_add_node_in_errors_log($output_context . "\n");
            }
            if ($this->_check_available_notify_time()) {
                if (file_exists($this->PATH . $this->SERVICE_FILE)) {
                    $notify_num = file_get_contents($this->PATH . $this->SERVICE_FILE);
                    if ($notify_num) {
                        $this->send_message($this->REMINDER_OF_NOTIFY . $notify_num);
                        file_put_contents($this->PATH . $this->SERVICE_FILE, '');
                    }
                }
            }
        } else {
            foreach ($messages as $message_string) {
                $this->_add_node_in_errors_log($messages . "\n");
                $message_parts = explode(' ', $message_string);
                $message_parts[2] = '<strong>' . $message_parts[2] . '</strong>';
                $output_context .= join(' ', $message_parts) . "<br>";
            }
            echo $output_context;
        }
    }

    /**
     * @param string $main_page
     * @param array $log_nodes
     * @internal
     */
    private function _parse_logs($main_page, $log_nodes)
    {
        $log_output_nodes = [];
        $notify_messages = [];
        $ignore_domain_errors_pages = false;
        $ignore_domain_anew_work_pages = false;
        $log_name = preg_replace('#^https?://#', '', $main_page);
        $full_log_name = $this->PATH . $this->LOG_PATH . $log_name;
        if (file_exists($full_log_name) && filemtime($full_log_name) > filemtime($this->PATH . 'sites.php')) {
            $current_log_data = file_get_contents($full_log_name);
            $current_log_data = explode("\n", $current_log_data);
            if (sizeof($current_log_data) === sizeof($log_nodes))
                for ($i = 0; $i < sizeof($current_log_data); $i++) {

                    // if the url response is ok(200) and previous response is ok(200)
                    if (
                        strpos($current_log_data[$i], ' GOOD ') != false &&
                        strpos($log_nodes[$i], ' GOOD ') !== false
                    ) {
                        $log_output_nodes[$i] = $log_nodes[$i];
                        $ignore_domain_errors_pages = false;
                    }

                    // if the url response is ok(200) and previous response is not ok
                    if (
                        strpos($current_log_data[$i], ' ERROR ') !== false &&
                        strpos($log_nodes[$i], ' GOOD ') !== false
                    ) {

                        $log_output_nodes[$i] = $log_nodes[$i];
                        $message_data = $this->_parse_log_node($current_log_data[$i]);

                        if ($i === 0) {
                            $notify_messages[] = $this->_join_output_message($message_data, true);
                            $ignore_domain_anew_work_pages = true;
                        } else {
                            if (!$ignore_domain_anew_work_pages) {
                                $notify_messages[] = $this->_join_output_message($message_data, true);
                            }
                        }
                    }

                    // if the url response is not ok and previous response is ok(200)
                    if (
                        strpos($current_log_data[$i], ' GOOD ') !== false &&
                        strpos($log_nodes[$i], ' ERROR ') !== false
                    ) {
                        $message_data = $this->_parse_log_node($log_nodes[$i]);

                        if ($i === 0) {
                            $notify_messages[] = $this->_join_output_message($message_data);
                            $ignore_domain_errors_pages = true;
                        } else {
                            if (!$ignore_domain_errors_pages) {
                                $notify_messages[] = $this->_join_output_message($message_data);
                            }
                        }

                        if ($this->_check_available_notify_time()) {
                            $log_output_nodes[$i] = $log_nodes[$i] . ' NOTIFY:1';
                        } else {
                            $log_output_nodes[$i] = $log_nodes[$i] . ' NOTIFY:0';
                        }
                    }

                    // if the url response is not ok and previous response is not ok
                    if (
                        strpos($current_log_data[$i], ' ERROR ') !== false &&
                        strpos($log_nodes[$i], ' ERROR ') !== false
                    ) {
                        if ($this->DISABLE_MILTY_NOTIFY) {

                            if (
                                $this->_parse_log_node($current_log_data[$i])['notify'] === 'NOTIFY:1' ||
                                file_exists($this->PATH . $this->SERVICE_FILE) &&
                                !$this->_check_available_notify_time() &&
                                file_get_contents($this->PATH . $this->SERVICE_FILE) !== ''
                            ) {
                                $log_output_nodes[$i] = $current_log_data[$i];
                            } else {
                                $message_data = $this->_parse_log_node($log_nodes[$i]);
                                if ($i === 0) {
                                    $notify_messages[] = $this->_join_output_message($message_data);
                                    $ignore_domain_errors_pages = true;
                                } else {
                                    if (!$ignore_domain_errors_pages) {
                                        $notify_messages[] = $this->_join_output_message($message_data);
                                    }
                                }
                                if ($this->_check_available_notify_time()) {
                                    $log_output_nodes[$i] = $this->_mark_node_as_read($current_log_data[$i]);
                                } else {
                                    $log_output_nodes[$i] = $current_log_data[$i];
                                }
                            }

                        } else {
                            $message_data = $this->_parse_log_node($log_nodes[$i]);
                            if ($i === 0) {
                                $notify_messages[] = $this->_join_output_message($message_data);
                                $ignore_domain_errors_pages = true;
                            } else {
                                if (!$ignore_domain_errors_pages) {
                                    $notify_messages[] = $this->_join_output_message($message_data);
                                }
                            }
                            $log_output_nodes[$i] = $current_log_data[$i];
                        }
                    }
                }

        } else {
            $log_output_nodes = $log_nodes;
            for ($i = 0; $i < sizeof($log_output_nodes); $i++) {
                if (strpos($log_output_nodes[$i], ' ERROR ') !== false) {
                    $message_data = $this->_parse_log_node($log_nodes[$i]);

                    if ($i === 0) {
                        $notify_messages[] = $this->_join_output_message($message_data);
                        $ignore_domain_errors_pages = true;
                    } else {
                        if (!$ignore_domain_errors_pages) {
                            $notify_messages[] = $this->_join_output_message($message_data);
                        }
                    }

                    if ($this->_check_available_notify_time()) {
                        $log_output_nodes[$i] .= ' NOTIFY:1';
                    } else {
                        $log_output_nodes[$i] .= ' NOTIFY:0';
                    }
                } else {
                    $ignore_domain_errors_pages = false;
                }
            }
        }
        file_put_contents($full_log_name, join("\n", $log_output_nodes));
        if (sizeof($notify_messages) !== 0)
            $this->_output_url_results($notify_messages);
    }

    /**
     * check all site in the $SITES_PATH
     */
    public function check_sites_stability()
    {
        if ($this->_check_first_run() && $this->_check_available_notify_time())
            $this->send_message($this->START_MESSAGE);
        foreach ($this->SITES as $site) {
            $log_nodes = [];
            $main_page = $site['domain'];
            $check_url_data = $this->check_url($main_page);

            if (!$check_url_data['is_available']) {
                sleep(2);
                $check_url_data = $this->check_url($main_page);
            }

            $log_nodes[] = $this->_create_log_node($main_page, $check_url_data['is_available'], $check_url_data['code']);
            if ($check_url_data['code'] != 0) {
                foreach ($site['pages'] as $page) {
                    $check_url_data = $this->check_url($main_page . $page);

                    if (!$check_url_data['is_available']) {
                        sleep(2);
                        $check_url_data = $this->check_url($main_page . $page);
                    }
                    $log_nodes[] = $this->_create_log_node($main_page . $page, $check_url_data['is_available'], $check_url_data['code']);
                }
            }
            $this->_parse_logs($main_page, $log_nodes);
        }
    }

    /**
     * @internal
     */
    private function _inc_disable_notify_num()
    {
        if (file_exists($this->PATH . $this->SERVICE_FILE)) {
            $notify_num = file_get_contents($this->PATH . $this->SERVICE_FILE);
            $notify_num++;
            file_put_contents($this->PATH . $this->SERVICE_FILE, $notify_num);
        } else {
            file_put_contents($this->PATH . $this->SERVICE_FILE, 1);
        }
    }

    /**
     * @return bool
     * @internal
     */
    private function _check_first_run()
    {
        $scan = scandir($this->PATH . $this->LOG_PATH);
        return sizeof($scan) == 2;
    }

    /**
     * @internal
     */
    private function _check_correct_data()
    {
        if ($this->SITES) {
            foreach ($this->SITES as $site) {

                if (!isset($site['domain']) || $site['domain'] === '' || !isset($site['pages'])) {
                    $this->_set_exit_error('ERROR: Incorrect data in $SITES');
                }
            }
        } else {
            $this->_set_exit_error('ERROR: $SITES is empty');
        }

        $verified_time = false;
        $all_hours = range(0, 24);
        foreach ($this->AVAILABLE_NOTIFY_HOURS as $time_interval) {
            $interval_edges = explode('-', $time_interval);
            try {
                if (is_numeric($interval_edges[0])
                    && is_numeric($interval_edges[1]) &&
                    (int)$interval_edges[0] < (int)$interval_edges[1] &&
                    in_array((int)$interval_edges[0], $all_hours) &&
                    in_array((int)$interval_edges[1], $all_hours)) {
                    $verified_time = true;
                } else
                    $this->_set_exit_error('ERROR: Bad time intervals');
            } catch (Exception $exception) {
                $this->_set_exit_error('ERROR: Bad time intervals');
            }
        }

        if (!$verified_time)
            $this->_set_exit_error('ERROR: Bad time intervals');

    }

    /**
     * @param string $message
     * @internal
     */
    private function _set_exit_error($message)
    {
        if ($this->_check_available_notify_time()) {
            $this->send_message($message);
        }
        if ($this->DEBUG)
            die($message);
        else
            die();
    }


    /**
     * send message to $CHAT_ID
     * @param string $message
     */
    public function send_message($message)
    {
        file_get_contents('https://api.telegram.org/bot' .
            $this->BOT_TOKEN . '/' . 'sendmessage?chat_id=' .
            $this->CHAT_ID . '&text=' . $message);
    }

    /**
     * @param $log_node
     * @internal
     */
    private function _add_node_in_errors_log($log_node)
    {
        if (file_exists($this->PATH . $this->LOG_PATH . $this->GENERAL_LOG_NAME)) {
            $file_data = file_get_contents($this->PATH . $this->LOG_PATH . $this->GENERAL_LOG_NAME);
            if (strpos($file_data, $log_node) === false) {
                if (!$this->_check_available_notify_time()) {
                    $this->_inc_disable_notify_num();
                }
                file_put_contents($this->PATH . $this->LOG_PATH . $this->GENERAL_LOG_NAME, $log_node, FILE_APPEND);
            }
        } else {
            file_put_contents($this->PATH . $this->LOG_PATH . $this->GENERAL_LOG_NAME, $log_node, FILE_APPEND);
        }
    }

    /**
     * @param $log_node
     * @return string
     * @internal
     */
    private function _mark_node_as_read($log_node)
    {
        $change_notify_str = explode(' NOTIFY:', $log_node);
        /**
         * what this code explode
         * $change_notify_str array(
         * 0=> '1970-01-01 00:00:00 GOOD 200 example.com NOTIFY:'
         * 1=> '0')
         */
        $change_notify_str[1] = '1';
        return join(' NOTIFY:', $change_notify_str);
    }

    /**
     * AdminBot constructor
     * @param array $settings
     * @param string $path
     */
    public function __construct($settings, $path)
    {
        ini_set('date.timezone', $settings['timezone']);
        $this->PATH = $path;
        $this->BOT_TOKEN = $settings['token'];
        $this->CHAT_ID = $settings['chat_id'];
        $this->SITES = $settings['sites'];
        $this->DEBUG = $settings['debug'];
        $this->DISABLE_MILTY_NOTIFY = $settings['disable_milty_notify'] ? $settings['disable_milty_notify']:["0-24"];
        $this->AVAILABLE_NOTIFY_HOURS = $settings['hours'];
        $this->_check_correct_data();
    }
}
