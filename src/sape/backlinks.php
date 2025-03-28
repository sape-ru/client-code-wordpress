<?php
/**
 * PHP client
 *
 * Publishers! Do not change anything in this file!
 * All settings are configured via parameters during the code call.
 *
 */

/**
 * Main class performing all routine tasks
 */
class BACKLINKS_base
{
    protected $_version = '1.5.5 (WP v3.4.4)';

    protected $_verbose = false;

    /**
     * Website encoding
     * @link http://www.php.net/manual/en/function.iconv.php
     * @var string
     */
    protected $_charset = '';

    protected $_backlinks_charset = '';

    protected $_server_list = array('dispenser.globalbacklinks.com');

    /**
     * Be gentle with our server :o)
     * @var int
     */
    protected $_cache_lifetime = 3600;

    /**
     * If backlink database download fails, retry after this many seconds
     * @var int
     */
    protected $_cache_reloadtime = 600;

    protected $_errors = array();

    protected $_host = '';

    protected $_request_uri = '';

    protected $_multi_site = true;

    /**
     * Remote server connection method  [file_get_contents|curl|socket]
     * @var string
     */
    protected $_fetch_remote_type = '';

    /**
     * Timeout for server response
     * @var int
     */
    protected $_socket_timeout = 6;

    protected $_force_show_code = false;

    /**
     * If our robot
     * @var bool
     */
    protected $_is_our_bot = false;

    protected $_debug                   = false;
    protected $_file_contents_for_debug = array();

    /**
     * Debug stack depth
     * @var int
     */
    protected $_debug_stack_max_deep    = 5;

    /**
     * Case-insensitive mode (use at your own risk)
     * @var bool
     */
    protected $_ignore_case = false;

    /**
     * Path to data file
     * @var string
     */
    protected $_db_file = '';

    /**
     * Request format: serialize|php-require
     * @var string
     */
    protected $_format = 'serialize';

    /**
     * Flag to split links.db into separate files
     * @var bool
     */
    protected $_split_data_file = true;
    /**
     * URI source: $_SERVER['REQUEST_URI'] or getenv('REQUEST_URI')
     * @var bool
     */
    protected $_use_server_array = false;

    /**
     * Display JS code separately from content
     *
     * @var bool
     */
    protected $_show_counter_separately = false;

    protected $_force_update_db = false;

    protected $_user_agent = '';

    /**
     * Mandatory output
     * @var string|null
     */
    protected $_page_obligatory_output = null;

    /**
     * Script launch options
     * @var array|mixed|null
     */
    protected $_options = array();

    /**
     * Requested URI
     * @var mixed|null
     */
    protected $_server_request_uri = null;
    protected $_getenv_request_uri = null;

    /**
     * User hash
     * @var string|null
     */
    protected $_BACKLINKS_USER = null;

    public function __construct($options = null)
    {

        // Let's go :o)

        $host = '';

        if (is_array($options)) {
            if (isset($options['host'])) {
                $host = $options['host'];
            }
        } elseif (strlen($options)) {
            $host    = $options;
            $options = array();
        } else {
            $options = array();
        }

        if (isset($options['use_server_array']) && $options['use_server_array'] == true) {
            $this->_use_server_array = true;
        }

        // Which website?
        if (strlen($host)) {
            $this->_host = $host;
        } else {
            $this->_host = $_SERVER['HTTP_HOST'];
        }

        $this->_host = mb_strtolower($this->_host, 'UTF-8');
        $this->_host = preg_replace('/^http:\/\//', '', $this->_host);
        $this->_host = preg_replace('/^www\./', '', $this->_host);

        // Which page?
        if (isset($options['request_uri']) && strlen($options['request_uri'])) {
            $this->_request_uri = $options['request_uri'];
        } elseif ($this->_use_server_array === false) {
            $this->_request_uri = getenv('REQUEST_URI');
        }

        if (strlen($this->_request_uri) == 0) {
            $this->_request_uri = $_SERVER['REQUEST_URI'];
        }

        // In case multiple websites share one folder
        if (isset($options['multi_site']) && $options['multi_site'] == true) {
            $this->_multi_site = true;
        }

        // Display debug information
        if (isset($options['debug']) && $options['debug'] == true) {
            $this->_debug = true;
        }

        //  Identifying our robot
        if (isset($_COOKIE['backlinks_cookie']) && ($_COOKIE['backlinks_cookie'] == _BACKLINKS_USER)) {
            $this->_is_our_bot = true;
            if (isset($_COOKIE['backlinks_debug']) && ($_COOKIE['backlinks_debug'] == 1)) {
                $this->_debug = true;
                // For debugging convenience (support)
                $this->_options            = $options;
                $this->_server_request_uri = $_SERVER['REQUEST_URI'];
                $this->_getenv_request_uri = getenv('REQUEST_URI');
                $this->_BACKLINKS_USER     = _BACKLINKS_USER;
            }
            if (isset($_COOKIE['backlinks_updatedb']) && ($_COOKIE['backlinks_updatedb'] == 1)) {
                $this->_force_update_db = true;
            }
        } else {
            $this->_is_our_bot = false;
        }

        // Report errors
        if (isset($options['verbose']) && $options['verbose'] == true || $this->_debug) {
            $this->_verbose = true;
        }

        // Encoding
        if (isset($options['charset']) && strlen($options['charset'])) {
            $this->_charset = $options['charset'];
        } else {
            $this->_charset = 'windows-1251';
        }

        if (isset($options['fetch_remote_type']) && strlen($options['fetch_remote_type'])) {
            $this->_fetch_remote_type = $options['fetch_remote_type'];
        }

        if (isset($options['socket_timeout']) && is_numeric($options['socket_timeout']) && $options['socket_timeout'] > 0) {
            $this->_socket_timeout = $options['socket_timeout'];
        }

        // Always output check-code
        if (isset($options['force_show_code']) && $options['force_show_code'] == true) {
            $this->_force_show_code = true;
        }

        if (!defined('_BACKLINKS_USER')) {
            return $this->_raise_error('_BACKLINKS_USER constant is not set');
        }

        // Ignore backlink case
        if (isset($options['ignore_case']) && $options['ignore_case'] == true) {
            $this->_ignore_case = true;
            $this->_request_uri = strtolower($this->_request_uri);
        }

        if (isset($options['show_counter_separately'])) {
            $this->_show_counter_separately = (bool)$options['show_counter_separately'];
        }

        if (isset($options['format']) && in_array($options['format'], array('serialize', 'php-require'))) {
            $this->_format = $options['format'];
        }

        if (isset($options['split_data_file'])) {
            $this->_split_data_file = (bool)$options['split_data_file'];
        }
    }

    /**
     * Retrieve User-Agent string
     *
     * @return string
     */
    protected function _get_full_user_agent_string()
    {
        return $this->_user_agent . ' ' . $this->_version;
    }

    /**
     * Output debug information
     *
     * @param $data
     *
     * @return string
     */
    protected function _debug_output($data)
    {
        $data = '<!-- <backlinks_debug_info>' .
            @base64_encode(serialize($this->prepare_debug_data($data, $this->_debug_stack_max_deep))) .
            '</backlinks_debug_info> -->'
        ;

        return $data;
    }

    /**
     * Function to connect to remote server
     */
    protected function _fetch_remote_file($host, $path, $specifyCharset = false)
    {

        $user_agent = $this->_get_full_user_agent_string();

        @ini_set('allow_url_fopen', 1);
        @ini_set('default_socket_timeout', $this->_socket_timeout);
        @ini_set('user_agent', $user_agent);
        if (
            $this->_fetch_remote_type == 'file_get_contents'
            ||
            (
                $this->_fetch_remote_type == ''
                &&
                function_exists('file_get_contents')
                &&
                ini_get('allow_url_fopen') == 1
            )
        ) {
            $this->_fetch_remote_type = 'file_get_contents';

            if ($specifyCharset && function_exists('stream_context_create')) {
                $opts    = array(
                    'http' => array(
                        'method' => 'GET',
                        'header' => 'Accept-Charset: ' . $this->_charset . "\r\n"
                    )
                );
                $context = @stream_context_create($opts);
                if ($data = @file_get_contents('http://' . $host . $path, null, $context)) {
                    return $data;
                }
            } else {
                if ($data = @file_get_contents('http://' . $host . $path)) {
                    return $data;
                }
            }
        } elseif (
            $this->_fetch_remote_type == 'curl'
            ||
            (
                $this->_fetch_remote_type == ''
                &&
                function_exists('curl_init')
            )
        ) {
            $this->_fetch_remote_type = 'curl';
            if ($ch = @curl_init()) {

                @curl_setopt($ch, CURLOPT_URL, 'http://' . $host . $path);
                @curl_setopt($ch, CURLOPT_HEADER, false);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->_socket_timeout);
                @curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
                if ($specifyCharset) {
                    @curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept-Charset: ' . $this->_charset));
                }

                $data = @curl_exec($ch);
                @curl_close($ch);

                if ($data) {
                    return $data;
                }
            }
        } else {
            $this->_fetch_remote_type = 'socket';
            $buff                     = '';
            $fp                       = @fsockopen($host, 80, $errno, $errstr, $this->_socket_timeout);
            if ($fp) {
                @fputs($fp, "GET {$path} HTTP/1.0\r\nHost: {$host}\r\n");
                if ($specifyCharset) {
                    @fputs($fp, "Accept-Charset: {$this->_charset}\r\n");
                }
                @fputs($fp, "User-Agent: {$user_agent}\r\n\r\n");
                while (!@feof($fp)) {
                    $buff .= @fgets($fp, 128);
                }
                @fclose($fp);

                $page = explode("\r\n\r\n", $buff);
                unset($page[0]);

                return implode("\r\n\r\n", $page);
            }
        }

        return $this->_raise_error('Unable to connect to the server: ' . $host . $path . ', type: ' . $this->_fetch_remote_type);
    }

    /**
     * Function to read from local file.
     */
    protected function _read($filename)
    {

        $fp = @fopen($filename, 'rb');
        if ($fp) {
            @flock($fp, LOCK_SH);
            clearstatcache();
            $length = @filesize($filename);

            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                $mqr = @get_magic_quotes_runtime();
                @set_magic_quotes_runtime(0);
            }

            if ($length) {
                $data = @fread($fp, $length);
            } else {
                $data = '';
            }

            if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                @set_magic_quotes_runtime($mqr);
            }

            @flock($fp, LOCK_UN);
            @fclose($fp);

            return $data;
        }

        return $this->_raise_error('Unable to read data from the file: ' . $filename);
    }

    /**
     * Function to write to local file
     */
    protected function _write($filename, $data)
    {

        $fp = @fopen($filename, 'ab');
        if ($fp) {
            if (flock($fp, LOCK_EX | LOCK_NB)) {
                ftruncate($fp, 0);

                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    $mqr = @get_magic_quotes_runtime();
                    @set_magic_quotes_runtime(0);
                }

                @fwrite($fp, $data);

                if (version_compare(PHP_VERSION, '5.3.0', '<')) {
                    @set_magic_quotes_runtime($mqr);
                }

                @flock($fp, LOCK_UN);
                @fclose($fp);

                if (md5($this->_read($filename)) != md5($data)) {
                    @unlink($filename);

                    return $this->_raise_error('Data integrity was violated while recording the file: ' . $filename);
                }
            } else {
                return false;
            }

            return true;
        }

        return $this->_raise_error('Unable to record the data to the file: ' . $filename);
    }

    /**
     * Error handling function
     */
    protected function _raise_error($e)
    {

        $this->_errors[] = $e;

        if ($this->_verbose == true) {
            print '<p style="color: red; font-weight: bold;">BACKLINKS ERROR: ' . $e . '</p>';
        }

        return false;
    }

    /**
     * Get data filename
     *
     * @return string
     */
    protected function _get_db_file()
    {
        return '';
    }

    /**
     * Get metadata filename
     *
     * @return string
     */
    protected function _get_meta_file()
    {
        return '';
    }

    /**
     * Get file prefix in split_data_file mode
     *
     * @return string
     */
    protected function _get_save_filename_prefix()
    {
        if ($this->_split_data_file) {
            return '.' . crc32($this->_request_uri) % 100;
        } else {
            return '';
        }
    }
    /**
     * Get host dispenser URI
     *
     * @return string
     */
    protected function _get_dispenser_path()
    {
        return '';
    }

    /**
     * Save data loaded from file to object
     */
    protected function _set_data($data)
    {
    }

    /**
     * Decrypt data
     *
     * @param string $data
     *
     * @return array|bool
     */
    protected function _uncode_data($data)
    {
        return @unserialize($data);
    }

    /**
     * Encrypt data for storage
     *
     * @param $data
     *
     * @return string
     */
    protected function _code_data($data)
    {
        return @serialize($data);
    }

    /**
     * Save data to file
     *
     * @param string $data
     * @param string $filename
     */
    protected function _save_data($data, $filename = '')
    {
        $this->_write($filename, $data);
    }
    /**
     * Load data
     */
    protected function _load_data()
    {
        $this->_db_file = $this->_get_db_file();

        if (!is_file($this->_db_file)) {
            // Пытаемся создать файл.
            if (@touch($this->_db_file, time() - $this->_cache_lifetime - 1)) {
                @chmod($this->_db_file, 0666); // File permissions
            } else {
                return $this->_raise_error($this->_db_file . ' file doesn\'t exist. Failed to create. Set permissions 777 for the folder');
            }
        }

        if (!is_writable($this->_db_file)) {
            return $this->_raise_error('Recording the file is not available: ' . $this->_db_file . '! Set the write permissions to the folder to 777');
        }

        @clearstatcache();

        $data = $this->_read($this->_db_file);
        if (
            $this->_force_update_db
            || (
                !$this->_is_our_bot
                &&
                (
                    filemtime($this->_db_file) < (time() - $this->_cache_lifetime)
                )
            )
        ) {
            // Prevent client site downtime and simultaneous requests
            @touch($this->_db_file, (time() - $this->_cache_lifetime + $this->_cache_reloadtime));

            $path = $this->_get_dispenser_path();
            if (strlen($this->_charset)) {
                $path .= '&charset=' . $this->_charset;
            }
            if ($this->_format) {
                $path .= '&format=' . $this->_format;
            }
            foreach ($this->_server_list as $server) {
                if ($data = $this->_fetch_remote_file($server, $path)) {
                    if (substr($data, 0, 12) == 'FATAL ERROR:') {
                        $this->_raise_error($data);
                    } else {
                        // [pseudo]integrity check:
                        $hash = $this->_uncode_data($data);
                        if ($hash != false) {
                            // attempt to cache encoding
                            $hash['__backlinks_charset__']      = $this->_charset;
                            $hash['__last_update__']       = time();
                            $hash['__multi_site__']        = $this->_multi_site;
                            $hash['__fetch_remote_type__'] = $this->_fetch_remote_type;
                            $hash['__ignore_case__']       = $this->_ignore_case;
                            $hash['__php_version__']       = phpversion();
                            $hash['__server_software__']   = $_SERVER['SERVER_SOFTWARE'];

                            $data_new = $this->_code_data($hash);
                            if ($data_new) {
                                $data = $data_new;
                            }

                            $this->_save_data($data, $this->_db_file);
                            break;
                        }
                    }
                }
            }
        }

        // Kill PHPSESSID
        if (strlen(session_id())) {
            $session            = session_name() . '=' . session_id();
            $this->_request_uri = str_replace(array('?' . $session, '&' . $session), '', $this->_request_uri);
        }
        $data = $this->_uncode_data($data);
        if ($this->_split_data_file) {
            $meta = $this->_uncode_data($this->_read($this->_get_meta_file()));
            if (!is_array($data)) {
                $data = array();
            }
            if (is_array($meta)) {
                $data = array_merge($data, $meta);
            }
        }
        $this->_set_data($data);

        return true;
    }

    protected function _return_obligatory_page_content()
    {
        $s_globals = new BACKLINKS_globals();

        $html = '';
        if (isset($this->_page_obligatory_output) && !empty($this->_page_obligatory_output)
            && false == $s_globals->page_obligatory_output_shown()
        ) {
            $s_globals->page_obligatory_output_shown(true);
            $html = $this->_page_obligatory_output;
        }

        return $html;
    }

    /**
     * Prepare data for debugging
     * @param mixed $data
     * @param int $max_deep Maximum traversal depth for arrays and nested objects
     * @return mixed
     */
    protected function prepare_debug_data(
        $data,
        $max_deep
    ) {
        if ($max_deep < 1 || $data instanceof \Closure) {
            return null;
        } elseif (is_array($data)) {
            $result = array();
            foreach ($data as $key => $value) {
                $result[$key] = $this->prepare_debug_data($value, $max_deep - 1);
            }

            return $result;
        } elseif (is_object($data)) {
            $result = array();
            $filledObjectFields = get_object_vars($data);
            foreach ($filledObjectFields as $key => $value) {
                $result[$key] = $this->prepare_debug_data($value, $max_deep - 1);
            }

            return $result;
        }

        return $data;
    }

    /**
     * Return JS code
     * - Only works when constructor parameter show_counter_separately = true
     * @return string
     */
    public function return_counter()
    {
        // If show_counter_separately = false and this method is called,
        // block JS code output along with content
        if (false == $this->_show_counter_separately) {
            $this->_show_counter_separately = true;
        }

        return $this->_return_obligatory_page_content();
    }
}

/**
 * Global flags
 */
class BACKLINKS_globals
{

    protected function _get_toggle_flag($name, $toggle = false)
    {

        static $flags = array();

        if (!isset($flags[$name])) {
            $flags[$name] = false;
        }

        if ($toggle) {
            $flags[$name] = true;
        }

        return $flags[$name];
    }

    public function block_css_shown($toggle = false)
    {
        return $this->_get_toggle_flag('block_css_shown', $toggle);
    }

    public function block_ins_beforeall_shown($toggle = false)
    {
        return $this->_get_toggle_flag('block_ins_beforeall_shown', $toggle);
    }

    public function page_obligatory_output_shown($toggle = false)
    {
        return $this->_get_toggle_flag('page_obligatory_output_shown', $toggle);
    }
}

/**
 * Class for handling regular backlinks
 */
class BACKLINKS_client extends BACKLINKS_base
{

    protected $_links_delimiter = '';
    protected $_links           = array();
    protected $_links_page      = array();
    protected $_teasers_page    = array();

    protected $_user_agent         = 'BACKLINKS_Client PHP';
    protected $_show_only_block    = false;
    protected $_block_tpl          = '';
    protected $_block_tpl_options  = array();
    protected $_block_uri_idna     = array();
    protected $_return_links_calls;
    protected $_teasers_css_showed = false;

    public function __construct($options = null)
    {
        parent::__construct($options);

        $this->_load_data();
    }

    /**
     * HTML processing for backlinks array
     *
     * @param string     $html
     * @param null|array $options
     *
     * @return string
     */
    protected function _return_array_links_html($html, $options = null)
    {

        if (empty($options)) {
            $options = array();
        }

        // If requested encoding differs from the cache encoding, convert accordingly
        if (
            strlen($this->_charset) > 0
            &&
            strlen($this->_backlinks_charset) > 0
            &&
            $this->_backlinks_charset != $this->_charset
            &&
            function_exists('iconv')
        ) {
            $new_html = @iconv($this->_backlinks_charset, $this->_charset, $html);
            if ($new_html) {
                $html = $new_html;
            }
        }

        if ($this->_is_our_bot) {

            $html = '<backlinks_noindex>' . $html . '</backlinks_noindex>';

            if (isset($options['is_block_links']) && true == $options['is_block_links']) {

                if (!isset($options['nof_links_requested'])) {
                    $options['nof_links_requested'] = 0;
                }
                if (!isset($options['nof_links_displayed'])) {
                    $options['nof_links_displayed'] = 0;
                }
                if (!isset($options['nof_obligatory'])) {
                    $options['nof_obligatory'] = 0;
                }
                if (!isset($options['nof_conditional'])) {
                    $options['nof_conditional'] = 0;
                }

                $html = '<backlinks_block nof_req="' . $options['nof_links_requested'] .
                    '" nof_displ="' . $options['nof_links_displayed'] .
                    '" nof_oblig="' . $options['nof_obligatory'] .
                    '" nof_cond="' . $options['nof_conditional'] .
                    '">' . $html .
                    '</backlinks_block>';
            }
        }

        return $html;
    }

    /**
     * Final HTML processing before backlinks output
     *
     * @param string $html
     *
     * @return string
     */
    protected function _return_html($html)
    {
        if (false == $this->_show_counter_separately) {
            $html = $this->_return_obligatory_page_content() . $html;
        }

        return $this->_add_debug_info($html);
    }

    protected function _add_debug_info($html)
    {
        if ($this->_debug) {
            if (!empty($this->_links['__backlinks_teaser_images_path__'])) {
                $this->_add_file_content_for_debug($this->_links['__backlinks_teaser_images_path__']);
            }
            $this->_add_file_content_for_debug('.htaccess');

            $html .= $this->_debug_output($this);
        }

        return $html;
    }

    protected function _add_file_content_for_debug($file_name)
    {
        $path                                               = realpath(
            rtrim($_SERVER['DOCUMENT_ROOT'], DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . strtok($file_name, '?')
        );
        $this->_file_contents_for_debug[$file_name]['path'] = $path;
        if ($path) {
            $this->_file_contents_for_debug[$file_name]['contents'] = @file_get_contents($path);
        }
    }

    /**
     * If requested encoding differs from the cache encoding, convert accordingly
     */
    protected function _convertCharset($html)
    {
        if (strlen($this->_charset) > 0
            && strlen($this->_backlinks_charset) > 0
            && $this->_backlinks_charset != $this->_charset
            && function_exists('iconv')
        ) {
            $new_html = @iconv($this->_backlinks_charset, $this->_charset, $html);
            if ($new_html) {
                $html = $new_html;
            }
        }

        return $html;
    }

    /**
     * Output backlinks as a block
     *
     * - Note: since version 1.2.2, the second argument $offset is removed.
     *  If passed using old signature, it will be ignored.
     *
     * @param int   $n       Number of backlinks to output in current block
     * @param array $options Options
     *
     * <code>
     * $options = array();
     * $options['block_no_css'] = (false|true);
     * // Overrides page CSS output restriction: false - output CSS
     * $options['block_orientation'] = (1|0);
     * // Overrides block orientation: 1 - horizontal, 0 - vertical
     * $options['block_width'] = ('auto'|'[?]px'|'[?]%'|'[?]');
     * // Overrides block width:
     * // 'auto'  - determined by parent block width; if none, occupies full width
     * // '[?]px' - pixel value
     * // '[?]%'  - percentage of parent block width
     * // '[?]'   - any other CSS-supported value
     * </code>
     *
     * @see return_links()
     * @see return_counter()
     *
     * @return string
     */
    public function return_block_links($n = null, $options = null)
    {

        $numargs = func_num_args();
        $args    = func_get_args();

        // Checking arguments for old calling signature
        if (2 == $numargs) {           // return_links($n, $options)
            if (!is_array($args[1])) { // return_links($n, $offset) - deprecated!
                $options = null;
            }
        } elseif (2 < $numargs) { // return_links($n, $offset, $options) - deprecated!

            if (!is_array($options)) {
                $options = $args[2];
            }
        }

        // Merge parameters
        if (empty($options)) {
            $options = array();
        }

        $defaults                      = array();
        $defaults['block_no_css']      = false;
        $defaults['block_orientation'] = 1;
        $defaults['block_width']       = '';

        $ext_options = array();
        if (isset($this->_block_tpl_options) && is_array($this->_block_tpl_options)) {
            $ext_options = $this->_block_tpl_options;
        }

        $options = array_merge($defaults, $ext_options, $options);

        // Backlinks provided not as array (check code) => output as is + block info
        if (!is_array($this->_links_page)) {
            $html = $this->_return_array_links_html('', array('is_block_links' => true));

            return $this->_return_html($this->_links_page . $html);
        } // No templates provided => cannot display block, do nothing
        elseif (!isset($this->_block_tpl)) {
            return $this->_return_html('');
        }

        // Determine necessary number of items in block

        $total_page_links = count($this->_links_page);

        $need_show_obligatory_block  = false;
        $need_show_conditional_block = false;
        $n_requested                 = 0;

        if (isset($this->_block_ins_itemobligatory)) {
            $need_show_obligatory_block = true;
        }

        if (is_numeric($n) && $n >= $total_page_links) {

            $n_requested = $n;

            if (isset($this->_block_ins_itemconditional)) {
                $need_show_conditional_block = true;
            }
        }

        if (!is_numeric($n) || $n > $total_page_links) {
            $n = $total_page_links;
        }

        // Select backlinks
        $links = array();
        for ($i = 1; $i <= $n; $i++) {
            $links[] = array_shift($this->_links_page);
        }

        $html = '';

        // Count optional blocks
        $nof_conditional = 0;
        if (count($links) < $n_requested && true == $need_show_conditional_block) {
            $nof_conditional = $n_requested - count($links);
        }

        //If no backlinks and no inserted blocks, output nothing
        if (empty($links) && $need_show_obligatory_block == false && $nof_conditional == 0) {

            $return_links_options = array(
                'is_block_links'      => true,
                'nof_links_requested' => $n_requested,
                'nof_links_displayed' => 0,
                'nof_obligatory'      => 0,
                'nof_conditional'     => 0
            );

            $html = $this->_return_array_links_html($html, $return_links_options);

            return $this->_return_html($html);
        }

        // Output styles only once, or don't output at all if specified in options
        $s_globals = new BACKLINKS_globals();
        if (!$s_globals->block_css_shown() && false == $options['block_no_css']) {
            $html .= $this->_block_tpl['css'];
            $s_globals->block_css_shown(true);
        }

        // Insert block at the beginning of all blocks
        if (isset($this->_block_ins_beforeall) && !$s_globals->block_ins_beforeall_shown()) {
            $html .= $this->_block_ins_beforeall;
            $s_globals->block_ins_beforeall_shown(true);
        }
        unset($s_globals);

        // Insert block at the beginning of current block
        if (isset($this->_block_ins_beforeblock)) {
            $html .= $this->_block_ins_beforeblock;
        }

        // Retrieve templates depending on block orientation
        $block_tpl_parts = $this->_block_tpl[$options['block_orientation']];

        $block_tpl          = $block_tpl_parts['block'];
        $item_tpl           = $block_tpl_parts['item'];
        $item_container_tpl = $block_tpl_parts['item_container'];
        $item_tpl_full      = str_replace('{item}', $item_tpl, $item_container_tpl);
        $items              = '';

        $nof_items_total = count($links);
        foreach ($links as $link) {

            // Regular styled backlink
            $is_found = preg_match('#<a href="(https?://([^"/]+)[^"]*)"[^>]*>[\s]*([^<]+)</a>#i', $link, $link_item);
            // Image-styled backlink
            if (!$is_found) {
                preg_match('#<a href="(https?://([^"/]+)[^"]*)"[^>]*><img.*?alt="(.*?)".*?></a>#i', $link, $link_item);
            }

            if (function_exists('mb_strtoupper') && strlen($this->_backlinks_charset) > 0) {
                $header_rest         = mb_substr($link_item[3], 1, mb_strlen($link_item[3], $this->_backlinks_charset) - 1, $this->_backlinks_charset);
                $header_first_letter = mb_strtoupper(mb_substr($link_item[3], 0, 1, $this->_backlinks_charset), $this->_backlinks_charset);
                $link_item[3]        = $header_first_letter . $header_rest;
            } elseif (function_exists('ucfirst') && (strlen($this->_backlinks_charset) == 0 || strpos($this->_backlinks_charset, '1251') !== false)) {
                $link_item[3][0] = ucfirst($link_item[3][0]);
            }

            // Replace decoded URL if present
            if (isset($this->_block_uri_idna) && isset($this->_block_uri_idna[$link_item[2]])) {
                $link_item[2] = $this->_block_uri_idna[$link_item[2]];
            }

            $item = $item_tpl_full;
            $item = str_replace('{header}', $link_item[3], $item);
            $item = str_replace('{text}', trim($link), $item);
            $item = str_replace('{url}', $link_item[2], $item);
            $item = str_replace('{link}', $link_item[1], $item);
            $items .= $item;
        }

        // Mandatory inserted element in block
        if (true == $need_show_obligatory_block) {
            $items .= str_replace('{item}', $this->_block_ins_itemobligatory, $item_container_tpl);
            $nof_items_total += 1;
        }

        // Optional inserted elements in block
        if ($need_show_conditional_block == true && $nof_conditional > 0) {
            for ($i = 0; $i < $nof_conditional; $i++) {
                $items .= str_replace('{item}', $this->_block_ins_itemconditional, $item_container_tpl);
            }
            $nof_items_total += $nof_conditional;
        }

        if ($items != '') {
            $html .= str_replace('{items}', $items, $block_tpl);

            // Assign uniform width everywhere
            if ($nof_items_total > 0) {
                $html = str_replace('{td_width}', round(100 / $nof_items_total), $html);
            } else {
                $html = str_replace('{td_width}', 0, $html);
            }

            // Override block width if specified
            if (isset($options['block_width']) && !empty($options['block_width'])) {
                $html = str_replace('{block_style_custom}', 'style="width: ' . $options['block_width'] . '!important;"', $html);
            }
        }

        unset($block_tpl_parts, $block_tpl, $items, $item, $item_tpl, $item_container_tpl);

        // Insert block at the end of current block
        if (isset($this->_block_ins_afterblock)) {
            $html .= $this->_block_ins_afterblock;
        }

        // Fill remaining modifiers with values
        unset($options['block_no_css'], $options['block_orientation'], $options['block_width']);

        $tpl_modifiers = array_keys($options);
        foreach ($tpl_modifiers as $k => $m) {
            $tpl_modifiers[$k] = '{' . $m . '}';
        }
        unset($m, $k);

        $tpl_modifiers_values = array_values($options);

        $html = str_replace($tpl_modifiers, $tpl_modifiers_values, $html);
        unset($tpl_modifiers, $tpl_modifiers_values);

        // Clear unused modifiers
        $clear_modifiers_regexp = '#\{[a-z\d_\-]+\}#';
        $html                   = preg_replace($clear_modifiers_regexp, ' ', $html);

        $return_links_options = array(
            'is_block_links'      => true,
            'nof_links_requested' => $n_requested,
            'nof_links_displayed' => $n,
            'nof_obligatory'      => ($need_show_obligatory_block == true ? 1 : 0),
            'nof_conditional'     => $nof_conditional
        );

        $html = $this->_return_array_links_html($html, $return_links_options);

        return $this->_return_html($html);
    }

    /**
     * Output backlinks as plain text with delimiter
     *
     * - Note: Since version 1.2.2, second argument $offset removed.
     * If passed according to old signature, it'll be ignored.
     *
     * @param int   $n       Количество ссылок, которые нужно вывести
     * @param array $options Опции
     *
     * <code>
     * $options = array();
     * $options['as_block'] = (false|true);
     * // Display backlinks as block
     * </code>
     *
     * @see return_block_links()
     * @see return_counter()
     *
     * @return string
     */
    public function return_links($n = null, $options = null)
    {

        if ($this->_debug) {
            if (function_exists('debug_backtrace')) {
                $this->_return_links_calls[] = debug_backtrace();
            } else {
                $this->_return_links_calls = "(function_exists('debug_backtrace')==false";
            }
        }

        $numargs = func_num_args();
        $args    = func_get_args();

        // Check arguments for old calling signature
        if (2 == $numargs) {           // return_links($n, $options)
            if (!is_array($args[1])) { // return_links($n, $offset) - deprecated!
                $options = null;
            }
        } elseif (2 < $numargs) {        // return_links($n, $offset, $options) - deprecated!

            if (!is_array($options)) {
                $options = $args[2];
            }
        }

        // Determine backlink output method
        $as_block = $this->_show_only_block;

        if (is_array($options) && isset($options['as_block']) && false == $as_block) {
            $as_block = $options['as_block'];
        }

        if (true == $as_block && isset($this->_block_tpl)) {
            return $this->return_block_links($n, $options);
        }

        //-------

        if (is_array($this->_links_page)) {

            $total_page_links = count($this->_links_page);

            if (!is_numeric($n) || $n > $total_page_links) {
                $n = $total_page_links;
            }

            $links = array();

            for ($i = 1; $i <= $n; $i++) {
                $links[] = array_shift($this->_links_page);
            }

            $html = $this->_convertCharset(join($this->_links_delimiter, $links));

            if ($this->_is_our_bot) {
                $html = '<backlinks_noindex>' . $html . '</backlinks_noindex>';
            }
        } else {
            $html = $this->_links_page;
            if ($this->_is_our_bot) {
                $html .= '<backlinks_noindex></backlinks_noindex>';
            }
        }

        $html = $this->_return_html($html);

        return $html;
    }

    public function return_teasers_block($block_id)
    {
        if ($this->_debug) {
            if (function_exists('debug_backtrace')) {
                $this->_return_links_calls[] = debug_backtrace();
            } else {
                $this->_return_links_calls = "(function_exists('debug_backtrace')==false";
            }
        }

        $html     = '';
        $template = @$this->_links['__backlinks_teasers_templates__'][$block_id];

        if (count($this->_teasers_page) && false == empty($template)) {

            if (count($this->_teasers_page) < $template['n']) {
                $teasers             = $this->_teasers_page;
                $to_add              = $template['n'] - count($this->_teasers_page);
                $this->_teasers_page = array();
            } else {
                $teasers             = array_slice($this->_teasers_page, 0, $template['n']);
                $to_add              = 0;
                $this->_teasers_page = array_slice($this->_teasers_page, $template['n']);
            }

            foreach ($teasers as $k => $v) {
                preg_match('#href="(https?://([^"/]+)[^"]*)"#i', $v, $url);
                $url         = empty($url[1]) ? '' : $url[1];
                $teasers[$k] = str_replace('{u}', $url, $template['bi'] . $v . $template['ai']);
            }

            if ($to_add) {
                $teasers = array_merge($teasers, array_fill($template['n'], $to_add, $template['e']));
            }

            $html = $this->_convertCharset(
                ($this->_teasers_css_showed ? '' : $this->_links['__backlinks_teasers_css__']) .
                str_replace('{i}', implode($template['d'], $teasers), $template['t'])
            );

            $this->_teasers_css_showed = true;
        } else {
            if ($this->_is_our_bot || $this->_force_show_code) {
                $html = $this->_links['__backlinks_new_teasers_block__'] . '<!-- ' . $block_id . ' -->';
            }
            if (!empty($template)) {
                $html .= str_replace('{id}', $block_id, $template['f']);
            } else {
                $this->_raise_error("Information about the $block_id, block doesn't exist, contact our support team");
            }
        }

        if ($this->_is_our_bot) {
            $html = '<backlinks_noindex>' . $html . '</backlinks_noindex>';
        }

        return $this->_add_debug_info($this->_return_obligatory_page_content() . $html);
    }

    public function show_image($file_name = null)
    {
        if ($this->_debug) {
            if (function_exists('debug_backtrace')) {
                $this->_return_links_calls[] = debug_backtrace();
            } else {
                $this->_return_links_calls = "(function_exists('debug_backtrace')==false";
            }
            echo $this->_add_debug_info('');
        }

        $file_name = $file_name ? $file_name : parse_url($this->_request_uri, PHP_URL_QUERY);

        if (!array_key_exists('__backlinks_teaser_images__', $this->_links) || !array_key_exists($file_name, $this->_links['__backlinks_teaser_images__'])) {
            $this->_raise_error("Image file named '$file_name' doesn't exist");
            header("HTTP/1.0 404 Not Found");
        } else {
            $extension = pathinfo(strtolower($file_name), PATHINFO_EXTENSION);
            if ($extension == 'jpg') {
                $extension = 'jpeg';
            }

            header('Content-Type: image/' . $extension);
            header('Content-Length: ' . strlen($this->_links['__backlinks_teaser_images__'][$file_name]));
            header('Cache-control: public, max-age=604800'); //1 week

            echo $this->_links['__backlinks_teaser_images__'][$file_name];
        }
    }

    protected function _get_db_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.links' . $this->_get_save_filename_prefix() . '.db';
        } else {
            return dirname(__FILE__) . '/links' . $this->_get_save_filename_prefix() . '.db';
        }
    }

    protected function _get_meta_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.links.meta.db';
        } else {
            return dirname(__FILE__) . '/links.meta.db';
        }
    }

    protected function _get_dispenser_path()
    {
        return '/code.php?user=' . _BACKLINKS_USER . '&host=' . $this->_host;
    }

    protected function _set_data($data)
    {
        if ($this->_ignore_case) {
            $this->_links = array_change_key_case($data);
        } else {
            $this->_links = $data;
        }
        if (isset($this->_links['__backlinks_delimiter__'])) {
            $this->_links_delimiter = $this->_links['__backlinks_delimiter__'];
        }
        // Determining cache encoding
        if (isset($this->_links['__backlinks_charset__'])) {
            $this->_backlinks_charset = $this->_links['__backlinks_charset__'];
        } else {
            $this->_backlinks_charset = '';
        }
        if (isset($this->_links) && is_array($this->_links)
            && @array_key_exists($this->_request_uri, $this->_links) && is_array($this->_links[$this->_request_uri])) {
            $this->_links_page = $this->_links[$this->_request_uri];
        } else {
            if (isset($this->_links['__backlinks_new_url__']) && strlen($this->_links['__backlinks_new_url__'])) {
                if ($this->_is_our_bot || $this->_force_show_code) {
                    $this->_links_page = $this->_links['__backlinks_new_url__'];
                }
            }
        }

        if (isset($this->_links['__backlinks_teasers__']) && is_array($this->_links['__backlinks_teasers__'])
            && @array_key_exists($this->_request_uri, $this->_links['__backlinks_teasers__']) && is_array($this->_links['__backlinks_teasers__'][$this->_request_uri])) {
            $this->_teasers_page = $this->_links['__backlinks_teasers__'][$this->_request_uri];
        }

        // Check for mandatory output
        if (isset($this->_links['__backlinks_page_obligatory_output__'])) {
            $this->_page_obligatory_output = $this->_links['__backlinks_page_obligatory_output__'];
        }

        // Check block backlink flag
        if (isset($this->_links['__backlinks_show_only_block__'])) {
            $this->_show_only_block = $this->_links['__backlinks_show_only_block__'];
        } else {
            $this->_show_only_block = false;
        }

        // Check template for styled backlinks
        if (isset($this->_links['__backlinks_block_tpl__']) && !empty($this->_links['__backlinks_block_tpl__'])
            && is_array($this->_links['__backlinks_block_tpl__'])
        ) {
            $this->_block_tpl = $this->_links['__backlinks_block_tpl__'];
        }

        // Check parameters for styled backlinks
        if (isset($this->_links['__backlinks_block_tpl_options__']) && !empty($this->_links['__backlinks_block_tpl_options__'])
            && is_array($this->_links['__backlinks_block_tpl_options__'])
        ) {
            $this->_block_tpl_options = $this->_links['__backlinks_block_tpl_options__'];
        }

        // IDNA domains
        if (isset($this->_links['__backlinks_block_uri_idna__']) && !empty($this->_links['__backlinks_block_uri_idna__'])
            && is_array($this->_links['__backlinks_block_uri_idna__'])
        ) {
            $this->_block_uri_idna = $this->_links['__backlinks_block_uri_idna__'];
        }

        // Blocks
        $check_blocks = array(
            'beforeall',
            'beforeblock',
            'afterblock',
            'itemobligatory',
            'itemconditional',
            'afterall'
        );

        foreach ($check_blocks as $block_name) {

            $var_name  = '__backlinks_block_ins_' . $block_name . '__';
            $prop_name = '_block_ins_' . $block_name;

            if (isset($this->_links[$var_name]) && strlen($this->_links[$var_name]) > 0) {
                $this->$prop_name = $this->_links[$var_name];
            }
        }
    }

    protected function _uncode_data($data)
    {
        if ($this->_format == 'php-require') {
            $data1 = str_replace('<?php return ', '', $data);
            eval('$data = ' . $data1 . ';');
            return $data;
        }

        return @unserialize($data);
    }

    protected function _code_data($data)
    {
        if ($this->_format == 'php-require') {
            return var_export($data, true);
        }

        return @serialize($data);
    }

    protected function _save_data($data, $filename = '')
    {
        if ($this->_split_data_file) {
            $directory = dirname(__FILE__) . '/';
            $hashArray = array();
            $data = $this->_uncode_data($data);
            foreach ($data as $url => $item) {
                if (preg_match('/\_\_.+\_\_/mu', $url)) {
                    $currentFile = 'links.meta.db';
                } else {
                    $currentFile = 'links.' . crc32($url) % 100 . '.db';
                }
                if ($this->_multi_site) {
                    $currentFile = $this->_host . '.' . $currentFile;
                }
                $hashArray[$currentFile][$url] = $item;
            }
            foreach ($hashArray as $file => $array) {
                $this->_write($directory . $file, $this->_code_data($array));
            }
            if (!isset($hashArray[basename($filename)])) {
                parent::_save_data('', $filename);
            }
        } else {
            parent::_save_data($data, $filename);
        }
    }
}

/**
 * Class for contextual backlink handling
 */
class BACKLINKS_context extends BACKLINKS_base
{

    protected $_words       = array();
    protected $_words_page  = array();
    protected $_user_agent  = 'BACKLINKS_Context PHP';
    protected $_filter_tags = array('a', 'textarea', 'select', 'script', 'style', 'label', 'noscript', 'noindex', 'button');

    protected $_debug_actions = array();

    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->_load_data();
    }

    /**
     * Start collecting debug info
     */
    protected function _debug_action_start()
    {
        if (!$this->_debug) {
            return;
        }

        $this->_debug_actions   = array();
        $this->_debug_actions[] = $this->_get_full_user_agent_string();
    }

    /**
     * Log debug string
     *
     * @param        $data
     * @param string $key
     */
    protected function _debug_action_append($data, $key = '')
    {
        if (!$this->_debug) {
            return;
        }

        if (!empty($key)) {
            $this->_debug_actions[] = array($key => $data);
        } else {
            $this->_debug_actions[] = $data;
        }
    }

    /**
     * Output debug information
     *
     * @return string
     */
    protected function _debug_action_output()
    {

        if (!$this->_debug || empty($this->_debug_actions)) {
            return '';
        }

        $debug_info = $this->_debug_output($this->_debug_actions);

        $this->_debug_actions = array();

        return $debug_info;
    }

    /**
     * Replace words in text chunk and wrap with baselinks_index tags
     */
    public function replace_in_text_segment($text)
    {

        $this->_debug_action_start();
        $this->_debug_action_append('START: replace_in_text_segment()');
        $this->_debug_action_append($text, 'argument for replace_in_text_segment');

        if (count($this->_words_page) > 0) {

            $source_sentences = array();

            // Create original texts array for replacement
            foreach ($this->_words_page as $n => $sentence) {
                // Replace entities with characters
                $special_chars = array(
                    '&amp;'  => '&',
                    '&quot;' => '"',
                    '&#039;' => '\'',
                    '&lt;'   => '<',
                    '&gt;'   => '>'
                );
                $sentence      = strip_tags($sentence);
                $sentence      = strip_tags($sentence);
                $sentence      = str_replace(array_keys($special_chars), array_values($special_chars), $sentence);

                // Convert special characters to entities
                $htsc_charset = empty($this->_charset) ? 'windows-1251' : $this->_charset;
                $quote_style  = ENT_COMPAT;
                if (version_compare(PHP_VERSION, '5.4.0') >= 0) {
                    $quote_style = ENT_COMPAT | ENT_HTML401;
                }

                $sentence = htmlspecialchars($sentence, $quote_style, $htsc_charset);

                // Quote characters
                $sentence      = preg_quote($sentence, '/');
                $replace_array = array();
                if (preg_match_all('/(&[#a-zA-Z0-9]{2,6};)/isU', $sentence, $out)) {
                    for ($i = 0; $i < count($out[1]); $i++) {
                        $unspec                 = $special_chars[$out[1][$i]];
                        $real                   = $out[1][$i];
                        $replace_array[$unspec] = $real;
                    }
                }
                // Replace entities with OR (entity|char)
                foreach ($replace_array as $unspec => $real) {
                    $sentence = str_replace($real, '((' . $real . ')|(' . $unspec . '))', $sentence);
                }
                // Replace spaces with line breaks or space entities
                $source_sentences[$n] = str_replace(' ', '((\s)|(&nbsp;)|( ))+', $sentence);
            }

            $this->_debug_action_append($source_sentences, 'sentences for replace');

            // if it's the first chunk, don't prepend <
            $first_part = true;
            // empty variable for writing

            if (count($source_sentences) > 0) {

                $content   = '';
                $open_tags = array(); // Open forbidden tags
                $close_tag = ''; // Current closing tag name

                // Split by tag start character
                $part = strtok(' ' . $text, '<');

                while ($part !== false) {
                    // Determine tag name
                    if (preg_match('/(?si)^(\/?[a-z0-9]+)/', $part, $matches)) {
                        // Determine tag name
                        $tag_name = strtolower($matches[1]);
                        // Check if it's a closing tag
                        if (substr($tag_name, 0, 1) == '/') {
                            $close_tag = substr($tag_name, 1);
                            $this->_debug_action_append($close_tag, 'close tag');
                        } else {
                            $close_tag = '';
                            $this->_debug_action_append($tag_name, 'open tag');
                        }
                        $cnt_tags = count($open_tags);
                        // If closing tag matches open forbidden tag
                        if (($cnt_tags > 0) && ($open_tags[$cnt_tags - 1] == $close_tag)) {
                            array_pop($open_tags);

                            $this->_debug_action_append($tag_name, 'deleted from open_tags');

                            if ($cnt_tags - 1 == 0) {
                                $this->_debug_action_append('start replacement');
                            }
                        }

                        // If no open bad tags, process
                        if (count($open_tags) == 0) {
                            // If not forbidden tag, start processing
                            if (!in_array($tag_name, $this->_filter_tags)) {
                                $split_parts = explode('>', $part, 2);
                                // Extra caution
                                if (count($split_parts) == 2) {
                                    // Iterate replacement phrases
                                    foreach ($source_sentences as $n => $sentence) {
                                        if (preg_match('/' . $sentence . '/', $split_parts[1]) == 1) {
                                            $split_parts[1] = preg_replace('/' . $sentence . '/', str_replace('$', '\$', $this->_words_page[$n]), $split_parts[1], 1);

                                            $this->_debug_action_append($sentence . ' --- ' . $this->_words_page[$n], 'replaced');

                                            // If replaced, remove line from replacement list
                                            unset($source_sentences[$n]);
                                            unset($this->_words_page[$n]);
                                        }
                                    }
                                    $part = $split_parts[0] . '>' . $split_parts[1];
                                    unset($split_parts);
                                }
                            } else {
                                // If forbidden tag, add to open tag stack
                                $open_tags[] = $tag_name;

                                $this->_debug_action_append($tag_name, 'added to open_tags, stop replacement');
                            }
                        }
                    } elseif (count($open_tags) == 0) {
                        // If no tag name, treat as text
                        foreach ($source_sentences as $n => $sentence) {
                            if (preg_match('/' . $sentence . '/', $part) == 1) {
                                $part = preg_replace('/' . $sentence . '/', str_replace('$', '\$', $this->_words_page[$n]), $part, 1);

                                $this->_debug_action_append($sentence . ' --- ' . $this->_words_page[$n], 'replaced');

                                //If replaced, remove line from replacement list, allowing multiple calls
                                unset($source_sentences[$n]);
                                unset($this->_words_page[$n]);
                            }
                        }
                    }

                    // If first part, omit <
                    if ($first_part) {
                        $content .= $part;
                        $first_part = false;
                    } else {
                        $content .= '<' . $part;
                    }
                    // Retrieve next chunk
                    unset($part);
                    $part = strtok('<');
                }
                $text = ltrim($content);
                unset($content);
            }
        } else {
            $this->_debug_action_append('No word\'s for page');
        }

        if ($this->_is_our_bot || $this->_force_show_code || $this->_debug) {
            $text = '<backlinks_index>' . $text . '</backlinks_index>';
            if (isset($this->_words['__backlinks_new_url__']) && strlen($this->_words['__backlinks_new_url__'])) {
                $text .= $this->_words['__backlinks_new_url__'];
            }
        }

        if (count($this->_words_page) > 0) {
            $this->_debug_action_append($this->_words_page, 'Not replaced');
        }

        $this->_debug_action_append('END: replace_in_text_segment()');

        $text .= $this->_debug_action_output();

        return $text;
    }

    /**
     * Replace words
     */
    public function replace_in_page($buffer)
    {

        $this->_debug_action_start();
        $this->_debug_action_append('START: replace_in_page()');

        $s_globals = new BACKLINKS_globals();

        if (!$s_globals->page_obligatory_output_shown()
            && isset($this->_page_obligatory_output)
            && !empty($this->_page_obligatory_output)
        ) {

            $split_content = preg_split('/(?smi)(<\/?body[^>]*>)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
            if (count($split_content) == 5) {
                $buffer = $split_content[0] . $split_content[1] . $split_content[2]
                    . (false == $this->_show_counter_separately ? $this->_return_obligatory_page_content() : '')
                    . $split_content[3] . $split_content[4];
                unset($split_content);

                $s_globals->page_obligatory_output_shown(true);
            }
        }

        if (count($this->_words_page) > 0) {
            // Split string by backlinks_index. Check for backlinks_index tags
            $split_content = preg_split('/(?smi)(<\/?backlinks_index>)/', $buffer, -1);
            $cnt_parts     = count($split_content);
            if ($cnt_parts > 1) {
                // If at least one backlinks_index pair exists, start processing
                if ($cnt_parts >= 3) {
                    for ($i = 1; $i < $cnt_parts; $i = $i + 2) {
                        $split_content[$i] = $this->replace_in_text_segment($split_content[$i]);
                    }
                }
                $buffer = implode('', $split_content);

                $this->_debug_action_append($cnt_parts, 'Split by Backlinks_index cnt_parts=');
            } else {
                // If no backlinks_index, attempt splitting by BODY
                $split_content = preg_split('/(?smi)(<\/?body[^>]*>)/', $buffer, -1, PREG_SPLIT_DELIM_CAPTURE);
                // If found content between body tags
                if (count($split_content) == 5) {
                    $split_content[0] = $split_content[0] . $split_content[1];
                    $split_content[1] = $this->replace_in_text_segment($split_content[2]);
                    $split_content[2] = $split_content[3] . $split_content[4];
                    unset($split_content[3]);
                    unset($split_content[4]);
                    $buffer = $split_content[0] . $split_content[1] . $split_content[2];

                    $this->_debug_action_append('Split by BODY');
                } else {
                    // If no backlinks_index found and BODY split failed
                    $this->_debug_action_append('Cannot split by BODY');
                }
            }
        } else {
            if (!$this->_is_our_bot && !$this->_force_show_code && !$this->_debug) {
                $buffer = preg_replace('/(?smi)(<\/?backlinks_index>)/', '', $buffer);
            } else {
                if (isset($this->_words['__backlinks_new_url__']) && strlen($this->_words['__backlinks_new_url__'])) {
                    $buffer .= $this->_words['__backlinks_new_url__'];
                }
            }

            $this->_debug_action_append('No word\'s for page');
        }

        $this->_debug_action_append('STOP: replace_in_page()');
        $buffer .= $this->_debug_action_output();

        return $buffer;
    }

    protected function _get_db_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.words' . $this->_get_save_filename_prefix() . '.db';
        } else {
            return dirname(__FILE__) . '/words' . $this->_get_save_filename_prefix() . '.db';
        }
    }

    protected function _get_meta_file()
    {
        if ($this->_multi_site) {
            return dirname(__FILE__) . '/' . $this->_host . '.words.meta.db';
        } else {
            return dirname(__FILE__) . '/words.meta.db';
        }
    }

    protected function _get_dispenser_path()
    {
        return '/code_context.php?user=' . _BACKLINKS_USER . '&host=' . $this->_host;
    }

    protected function _set_data($data)
    {
        $this->_words = $data;
        if (isset($this->_words) && is_array($this->_words)
            && @array_key_exists($this->_request_uri, $this->_words) && is_array($this->_words[$this->_request_uri])) {
            $this->_words_page = $this->_words[$this->_request_uri];
        }

        // Check mandatory output
        if (isset($this->_words['__backlinks_page_obligatory_output__'])) {
            $this->_page_obligatory_output = $this->_words['__backlinks_page_obligatory_output__'];
        }
    }

    protected function _uncode_data($data)
    {
        if ($this->_format == 'php-require') {
            $data1 = str_replace('<?php return ', '', $data);
            eval('$data = ' . $data1 . ';');
            return $data;
        }

        return @unserialize($data);
    }

    protected function _code_data($data)
    {
        if ($this->_format == 'php-require') {
            return var_export($data, true);
        }

        return @serialize($data);
    }

    protected function _save_data($data, $filename = '')
    {
        if ($this->_split_data_file) {
            $directory = dirname(__FILE__) . '/';
            $hashArray = array();
            $data = $this->_uncode_data($data);
            foreach ($data as $url => $item) {
                if (preg_match('/\_\_.+\_\_/mu', $url)) {
                    $currentFile = 'words.meta.db';
                } else {
                    $currentFile = 'words.' . crc32($url) % 100 . '.db';
                }
                if ($this->_multi_site) {
                    $currentFile = $this->_host . '.' . $currentFile;
                }
                $hashArray[$currentFile][$url] = $item;
            }
            foreach ($hashArray as $file => $array) {
                $this->_write($directory . $file, $this->_code_data($array));
            }
            if (!isset($hashArray[basename($filename)])) {
                parent::_save_data('', $filename);
            }
        } else {
            parent::_save_data($data, $filename);
        }
    }
}
