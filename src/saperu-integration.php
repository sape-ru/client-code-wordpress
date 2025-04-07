<?php
/*
Plugin Name: Sape.ru integration
Plugin URI: https://github.com/sape-ru/client-code-wordpress/releases
Description: Plugin for Sape.ru webmaster services integration
Version: 3.4.4
Author: Sape.ru
Author URI: http://www.sape.ru/
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages
Text Domain: sape-api
*/

if ( ! function_exists( 'boolval' ) ) {
    function boolval( $val ) {
        return (bool) $val;
    }
}

if ( ! function_exists( 'intval' ) ) {
    function intval( $val ) {
        return (int) $val;
    }
}

class Sape_API {

    private static $_options = array(
        'sape_user'             => '', // like d12d0d074c7ba7f6f78d60e2bb560e3f
        'sape_part_is_client'   => true,
        'sape_part_is_context'  => true,
        'sape_part_is_tizer'    => false,
        'sape_widget_class'     => 'advert',
        'sape_login'            => ' ',
        'sape_password'         => ' ',
    );

    // is `wp-content/upload` because this dir always writable
    private static $_sape_path;

    private $_sape_options = array(
        'charset'                 => 'UTF-8', // since WP 3.5 site encoding always utf-8
        'multi_site'              => true,
        'show_counter_separately' => true,
        'force_show_code' => false
    );

    /** @var SAPE_client */
    private $_sape_client;

    /** @var SAPE_context */
    private $_sape_context;

    private $_plugin_basename;

    private $_sape_context_replace_texts;

    public function __construct() {
        $this->_plugin_basename = plugin_basename( __FILE__ );
        // misc
        load_plugin_textdomain( 'sape-api', false, dirname( $this->_plugin_basename ) . '/languages' );
        register_activation_hook( __FILE__, array( __CLASS__, 'activation_hook' ) );
        register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivation_hook' ) );
        register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall_hook' ) );

        // init
        add_action('init', array(&$this, 'init'));

        // updrage
        add_action('upgrader_process_complete', array(&$this, 'upgrade'), 10, 2);

        //
        add_action( 'plugins_loaded', 'true_load_plugin_textdomain' );
        function true_load_plugin_textdomain() {
            load_plugin_textdomain( 'sapeTranslate', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
        }

        // _BACKLINKS_USER
        if ( ! defined( '_BACKLINKS_USER' ) ) {
            define( '_BACKLINKS_USER', get_option( 'sape_user' ) );
        } else {
            if ( is_admin() ) {
                add_action( 'admin_init', function () {
                    add_action( 'admin_notices', function () {
                        echo '<div class="update-nag"><p>';
                        echo sprintf( __('Константа %s уже определена ранее!', 'sapeTranslate'), '<code>_BACKLINKS_USER</code>' );
                        echo ' ';
                        echo sprintf( __('Настройки плагина %s не применены!', 'sapeTranslate'), '<code>Sape.ru integration</code>' );
                        echo '</p></div>';
                    } );
                } );
            }
        }

        $this->_registerLinks();
        $this->_registerContext();
        $this->_registerTizer();
        $this->_registerCounter();
    }

    protected function _registerLinks()
    {
        if ( get_option( 'sape_part_is_client' ) ) {
            add_action( 'widgets_init', function () {
                register_widget( 'Sape_API_Widget_Links' );
            }, 1 );

            add_shortcode( 'sape', array( &$this, 'shortcode_sape' ) );
            add_filter( 'no_texturize_shortcodes', function ( $list ) {
                $list[] = 'sape';

                return $list;
            } );
            add_action( 'wp_footer', array( &$this, 'render_remained_links' ), 1 );
        }
    }

    protected function _registerContext()
    {
        if ( get_option( 'sape_part_is_context' ) && _BACKLINKS_USER !== '' ) {
            add_filter( 'the_content', array( &$this, '_sape_replace_in_text_segment' ), 11, 1);
            add_filter( 'the_excerpt', array( &$this, '_sape_replace_in_text_segment' ), 11, 1 );
            remove_filter( 'the_content', 'do_shortcode' );
            remove_filter( 'the_excerpt', 'do_shortcode' );
            add_filter( 'the_content', 'do_shortcode', 12 );
            add_filter( 'the_excerpt', 'do_shortcode', 12 );
        }
    }

    protected function _registerTizer()
    {
        if ( get_option( 'sape_part_is_tizer' ) && _BACKLINKS_USER !== '' ) {

            add_action( 'widgets_init', function () {
                register_widget( 'Sape_API_Widget_Tizer' );
            }, 2 );

            add_shortcode('sape_tizer', array(&$this, 'shortcode_sape_tizer'));

            add_filter('no_texturize_shortcodes', function ($list) {
                $list[] = 'sape_tizer';

                return $list;
            });
        }
    }

    protected function _registerCounter()
    {
        if ( _BACKLINKS_USER !== '' ) {
            add_action( 'wp_footer', array( &$this, '_sape_return_counter' ), 1 );
        }
    }

    public function render_remained_links() {
        //if ( $this->_getSapeClient()->_links_page > 0 ) {
        echo do_shortcode( '[sape block=1 orientation=1]' );
        //}
    }

    public function init() {
        // admin panel
        add_action( 'admin_init', array( &$this, 'admin_init' ), 1 ); // init settings
        add_action( 'admin_menu', array( &$this, 'admin_menu' ), 1 ); // create page
        add_filter( 'plugin_action_links_' . $this->_plugin_basename, array( &$this, 'plugin_action_links' ) ); # links
        add_filter( 'plugin_row_meta', array( &$this, 'plugin_row_meta' ), 1, 2 ); # plugins meta

        // show code on front page -- need to add site to sape system
        if ( is_front_page() ) {
            add_action( 'wp_footer', array( &$this, '_sape_return_links' ), 1 );
        }
    }

    public function upgrade($upgrader_object, $options) {
        $current_plugin_path_name = plugin_basename( __FILE__ );
        if ($options['action'] == 'update' && $options['type'] == 'plugin' ) {
            foreach($options['plugins'] as $each_plugin){
                if ($each_plugin == $current_plugin_path_name) {
                    self::activation_hook();
                }
            }
        }
    }

    public static function activation_hook() {
        // init options
        foreach ( self::$_options as $option => $value ) {
            add_option( $option, $value );
        }

        // let make dir and copy sape's files to uploads/.sape/
        if ( ! wp_mkdir_p( self::_getSapePath() ) ) {
            $activationFailedMessage = sprintf( __('Директория %s не доступна для записи.', 'sapeTranslate'), '<i>`' . ABSPATH . WPINC . '/upload' . '`</i>' );
            self::chmod_wrong_on_activation($activationFailedMessage);
        }

        // let copy file to created dir
        $local_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'sape';

        $files = array(
            $local_path . DIRECTORY_SEPARATOR . 'backlinks.php' => self::_getSapePath() . DIRECTORY_SEPARATOR . 'backlinks.php',
            $local_path . DIRECTORY_SEPARATOR . '.htaccess' => self::_getSapePath() . DIRECTORY_SEPARATOR . '.htaccess'
        );

        foreach ($files as $filePathFrom => $filePathTo) {
            if (!copy( $filePathFrom, $filePathTo)) {
                $activationFailedMessage = sprintf( __('Файл %s не доступен для записи.', 'sapeTranslate'), '<i>`' . $filePathTo . '`</i>');
                self::chmod_wrong_on_activation($activationFailedMessage);
            }
        }
    }

    public static function chmod_wrong_on_activation($activationFailedMessage) {
        $path = plugin_basename( __FILE__ );
        deactivate_plugins( $path );

        $link        = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $path ), 'activate-plugin_' . $path );
        $string      = '';
        $string .= $activationFailedMessage . '.<br/>';
        $string .= sprintf( __('Исправьте и активируйте плагин %s заново.', 'sapeTranslate'), '<b>' . $path . '</b>' ) . '.<br/>';
        $string .= '<a href="' . $link . '" class="edit">' . __( 'Активировать', 'sapeTranslate') . '</a>';

        wp_die( $string );
    }

    public static function chmod_wrong_on_save_options($saveFailedMessage) {
        $string      = '';
        $string .= $saveFailedMessage . '<br/>';
        $string .= sprintf(__('или исправить права доступа и %s настроить %s плагин заново.', 'sapeTranslate'), '<a href="admin.php?page=page_sape">', '</a>');

        wp_die( $string );
    }

    public static function deactivation_hook() {
        // clear cache?
    }

    public static function uninstall_hook() {
        // delete options
        foreach ( self::$_options as $option => $value ) {
            delete_option( $option );
        }

        // delete sape's files
        self::_deleteDir( self::_getSapePath() );
    }

    private static function _deleteDir( $path ) {
        $class_func = array( __CLASS__, __FUNCTION__ );

        return is_file( $path ) ? @unlink( $path ) : array_map( $class_func, glob( $path . '/*' ) ) == @rmdir( $path );
    }

    private static function _getSapePath() {
        if ( self::$_sape_path === null ) {
            self::$_sape_path = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . '.sape';
        }

        return self::$_sape_path;
    }

    private function _getSapeClient() {
        if ( $this->_sape_client === null ) {
            include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'backlinks.php';
            $this->_sape_client = new BACKLINKS_client( $this->_sape_options );
        }

        return $this->_sape_client;
    }
    private function _sape_return_links( $count, $options ) {
        return $this->_getSapeClient()->return_links( $count, $options );
    }
    private function _sape_return_tizer( $ID ) {
        return $this->_getSapeClient()->return_teasers_block( (int)$ID );
    }
    public function _sape_return_counter() {
        $counterHtml = '';
        if ($counterHtml == '') {
            $counterHtml = $this->_getSapeClient()->return_counter();
        }
        echo $counterHtml;
    }

    private function _getSapeContext() {
        if ( $this->_sape_context === null ) {
            include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'backlinks.php';
            $this->_sape_context = new BACKLINKS_context( $this->_sape_options );
        }

        return $this->_sape_context;
    }

    public function _sape_replace_in_text_segment( $text ) {
        $hash = md5($text);

        if (!isset($this->_sape_context_replace_texts[$hash])) {
            $this->_sape_context_replace_texts[$hash] = $this->_getSapeContext()->replace_in_text_segment( $text );
        }

        return $this->_sape_context_replace_texts[$hash];
    }

    public function shortcode_sape( $atts, $content = null ) {
        $atts = shortcode_atts( array(
                                    'count'       => null,
                                    'block'       => 0,
                                    'orientation' => 0,
                                    'force_show_code' => false
                                ), $atts );

        $this->_sape_options['force_show_code'] = $atts['force_show_code'];

        $text = $this->_sape_return_links(
            $atts['count'],
            array(
                'as_block'          => $atts['block'] == 1,
                'block_orientation' => $atts['orientation'],
            )
        );

        return ! empty( $text ) ? $text : $content;
    }

    public function shortcode_sape_tizer( $atts, $content = null ) {
        $atts = shortcode_atts( array(
                                    'id'       => null,
                                ), $atts );



        $text = $this->_sape_return_tizer(
            $atts['id']
        );

        return ! empty( $text ) ? $text : $content;
    }

    public function plugin_action_links( $links ) {
        unset( $links['edit'] );
        $settings_link = '<a href="admin.php?page=page_sape">' . __('Настройки', 'sapeTranslate') . '</a>';
        array_unshift( $links, $settings_link );

        return $links;
    }

    public function plugin_row_meta( $links, $file ) {
        if ( $file == $this->_plugin_basename ) {
            $settings_link = '<a href="admin.php?page=page_sape">' . __('Настройки', 'sapeTranslate') . '</a>';
            $links[]       = $settings_link;
            $links[]       = 'Code is poetry!';
        }

        return $links;
    }

    public function admin_menu() {
        add_menu_page(
            'Sape ' . __( 'Настройки', 'sapeTranslate'), // title
            __('Монетизация сайта - Sape', 'sapeTranslate'), // menu title
            'manage_options', // capability
            'page_sape', // menu slug
            array( &$this, 'page_sape' ), // callback
            'data:image/jpeg;base64,/9j/4AAQSkZJRgABAQIAHAAcAAD/2wBDAAEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/2wBDAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQEBAQH/wAARCAAQABADAREAAhEBAxEB/8QAFgABAQEAAAAAAAAAAAAAAAAABwYK/8QAIBAAAQUBAQACAwAAAAAAAAAABAIDBQYHCAEACRESE//EABkBAAIDAQAAAAAAAAAAAAAAAAIFAwQHCf/EACcRAAIDAAICAgEDBQAAAAAAAAECAwQFBhESIQAHExUiIzEyQWGB/9oADAMBAAIRAxEAPwDPbxzwxVOhMX6E6Z2TbjcQwLnCUzutW+ZqeVT+16DLWnT35ZqthxFFgZaAQHBDNQphE3aJubAjAf2YGb/sQ77432X5pz23xzb45xfFwk3uRcmh0rVKC3r18LOhqZSwmy82hYhsF7DmdFgqwQSSv0zHxUDvPK1RZopZ5ZfxRQlFYrG0rln78QFBHr17JPQ+H2lWTmHL832Ln7Nq5UOk5qx37Prrl3ayg79m1uq9MDgWn7Hm6cnnvViDvHSBbsfPEGGGsMnjFOxxcqMiKLGY5dblOrp43ItOzc4xBWz9Gjq8GD5+pTtXXsFa2n+sV+nZY40WSuqIjNGyCRIWMyMDtBGkkKBZizoyWenRlXr9yfjP+/R9n330T6+Mv1x6I3lsjoFrq32OSHAWo+uwAEeiaza/3zK9XpakSD05G3NNHjbaMSdDneBqja7a6LKwsmMaYpuSCfSptaX7LzjrRZ1O39aR/YeUBYkkMOnnZ+tkXu4xBLRN+WmyRzJ5iWzUvwzxOidxyKR1JScIXZbhqSeuu0Z45F99hvEMOweugykHs/JX7SNj5y3bruzaDzFGRbVGJpOcw1rtUDQmssruo63B1YMDTtWr2bMoZRSoe82ZsiTEhfRg3fPws18MZ4xxHy39U4vJcDhtTO5TLK19b2nPUqWNA61nKx7Ft5MrIs6ZLG9NQqlYnn8nH9EV2VAfg35YZbDPAB4+KBmCCNZJAv8AJIEH9oZvYH/f8/P/2Q=='
        );

        add_submenu_page(
            'page_sape',
            'Sape ' . __( 'Настройки', 'sapeTranslate' ), // title
            __( 'Настройки', 'sapeTranslate'), // menu title
            'manage_options', // capability
            'page_sape', // menu slug
            array( &$this, 'page_sape' ) // callback
        );
    }

    public function page_sape() {
        ?>
      <div class="wrap">

        <h1><?php _e('Монетизация сайта - Sape', 'sapeTranslate') ?></h1>

        <form action="options.php" method="post" novalidate="novalidate">

            <?php
            settings_fields( 'sape_base' );
            do_settings_sections( 'page_sape' );
            submit_button();
            ?>

        </form>

      </div>
        <?php
    }

    public function admin_init() {
        // register settings `base`
        register_setting( 'sape_base', 'sape_user', 'trim' );
        register_setting( 'sape_base', 'sape_part_is_client', 'boolval' );
        register_setting( 'sape_base', 'sape_part_is_context', 'boolval' );

        register_setting( 'sape_base', 'sape_part_is_tizer', 'boolval' );
        register_setting( 'sape_base', 'sape_part_is_tizer_image', array('type'=>'intval', 'sanitize_callback' => array( &$this, 'change_field_tizer_image')) );
        register_setting( 'sape_base', 'sape_widget_class', 'trim' );

        // add sections
        add_settings_section(
            'section__sape_identification', // id
            __('Задайте ваш ключ пользователя', 'sapeTranslate'), // title
            function () {
                echo '<br/>';
            }, // callback
            'page_sape' // page
        );

        add_settings_section(
            'section__sape_parts', // id
            __('Форматы монетизации', 'sapeTranslate'), // title
            function () {
                _e('Активируйте нужные вам форматы монетизации.', 'sapeTranslate');
                echo '<br/>';
                echo '<br/>';
            }, // callback
            'page_sape' // page
        );

        // add fields
        add_settings_field(
            'sape_user', // id
            __('Ключ Пользователя', 'sapeTranslate'), // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_identification', // section
            array(
                'label_for' => 'sape_user',
                'type'      => 'text',
                'descr'     =>
                    __('Ключ Пользователя - это ваш уникальный идентификатор (хеш).', 'sapeTranslate') .'<br/>'.
                    sprintf(__('Можете найти его на %s странице добавления нового сайта%s в вашем аккаунте.', 'sapeTranslate'), '<a target="_blank" href="//www.sape.ru/site.php?act=add#WordPress/">', '</a>') . '<br/>' .
                    sprintf(__('Ключ Пользователя похож на что-то вроде %s d12d0dx074c7ba7f6f78d60e2bb560e3f %s.', 'sapeTranslate'), '<b>','</b>') .' ' .
                    __('Укажите ваш Ключ Пользователя и плагин всё сделает автоматически (вам не нужно будет загружать файлы или архивы вручную).', 'sapeTranslate')
            ) // args
        );

        add_settings_field(
            'sape_part_is_client', // id
            __('Арендные ссылки', 'sapeTranslate'), // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_client',
                'type'      => 'checkbox',
                'descr'     =>
                    '<br/>' .
                    sprintf(__('После активации будет доступен как %s виджет%s для вывода ссылок, так и шорткод:', 'sapeTranslate'), '<a target="_blank" href="' . admin_url( 'widgets.php' ) . '">', '</a>')
                    .'<br/>
                      <code>[sape]</code> - '. __('вывод всех ссылок в формате текста', 'sapeTranslate'). '<br/>
                      <code>[sape force_show_code = 1]</code> - '. __('принудительно показать проверочный код', 'sapeTranslate'). '<br/>
                      <code>[sape count=2]</code> - ' .__('вывод лишь двух ссылок', 'sapeTranslate') .'<br/>
                      <code>[sape count=2 block=1]</code> - ' .__('вывод ссылок в формате блока', 'sapeTranslate') .'<br/>
                      <code>[sape count=2 block=1 orientation=1]</code> - ' .__('вывод ссылок в формате блока горизонтально', 'sapeTranslate'). '<br/>
                      <code>[sape] html, js[/sape]</code> - ' .__('вывод альтернативного текста при отсутствии ссылок.', 'sapeTranslate'). '<br/>'.
                      __('Для вывода внутри темы (шаблона) используйте следующий код:', 'sapeTranslate'). '<code>' . esc_attr( '<?php echo do_shortcode(\'[sape]\') ?>' ) . '</code>'.
                    '.<br/>'.
                    sprintf( __('Если вы видите не все проданные ссылки на странице, то оставшиеся добавятся в футер (подвал) сайта во избежание появления у ссылок статуса %s.', 'sapeTranslate'), '<code>ERROR</code>' )
            ,
            ) // args
        );

        add_settings_field(
            'sape_part_is_context', // id
            __('Контекстные ссылки', 'sapeTranslate'), // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_context',
                'type'      => 'checkbox',
                'descr'     => __('Ссылки, размещенные непосредственно внутри существующего контента страниц.', 'sapeTranslate'),
            ) // args
        );

        add_settings_field(
            'sape_part_is_tizer', // id
            __('Ссылки-тизеры', 'sapeTranslate'), // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_tizer',
                'type'      => 'checkbox',
                'descr'     =>
                    __('Ссылки размещаемые в формате тизерных блоков.', 'sapeTranslate'). '<br/>'.
                    sprintf(__('После активации будет доступен как %s виджет%s для вывода тизерных блоков, так и шорткод:%s', 'sapeTranslate'), '<a target="_blank" href="' . admin_url( 'widgets.php' ) . '">', '</a>', '<br/>')
                    .'<code>[sape_tizer id=1]</code> - ' .__('вывод тизерного блока, с ID 1', 'sapeTranslate') .'<br/>
<code>[sape_tizer] html, js[/sape_tizer]</code> - ' .__('вывод альтернативного текста при отсутствии тизерного блока.', 'sapeTranslate') .'<br/>'.
                    __('Для вывода внутри темы (шаблона) используйте следующий код:', 'sapeTranslate') .'<code>' . esc_attr( '<?php echo do_shortcode(\'[sape_tizer id=ID_БЛОКА]\') ?>' ) . '</code>.',
            ) // args
        );

        add_settings_field(
            'sape_part_is_tizer_image', // id
            __('Файл изображения тизеров', 'sapeTranslate'), // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_tizer_image',
                'type'      => 'select',
                'descr'     => __('Имя файла, показывающего картинки тизеров.', 'sapeTranslate'),
                'options' => $this-> _getTizerImageOptions()
            ) // args
        );
    }

    function change_field_tizer_image($args)
    {
        $SID = get_option('sape_user');

        if ($SID) {
            $file_name = $this->_getTizerImageOptions($args);
            if(isset($file_name) && !is_array($file_name) && $file_name <> '') {
                $dir = self::_getSapePath() . DIRECTORY_SEPARATOR . 'backlinks.php';
                $data = sprintf('<?php define(\'_BACKLINKS_USER\', \'%s\');require_once(\'%s\');$sape = new BACKLINKS_client(array(\'charset\' => \'UTF-8\'));$sape->show_image();', $SID, $dir);
                $fileName = $_SERVER['DOCUMENT_ROOT'].'/'.$file_name;
                if (!file_put_contents($fileName, $data)) {
                    $userData = file_get_contents($fileName);
                    if ($userData !== $data) {
                        $message = 'Sape: ' . sprintf( __('папка %s не доступна для записи.', 'sapeTranslate'), '<i>`' . $_SERVER['DOCUMENT_ROOT'] . '`</i>');
                        $message .= '<p>' .sprintf(__('Вы можете создать файл %s вручную с содержимым:', 'sapeTranslate'), '<i>`' . $fileName . '`</i>') .'</p>';
                        $message .= '<p>' . htmlentities($data) . '</p>';
                        self::chmod_wrong_on_save_options($message);

                    }
                }
            }
        }

        return $args;
    }

    protected function _getTizerImageOptions($id = null)
    {
        if(isset($id)) {
            $data = $this->_getTizerImageOptions();
            return isset($data[$id]) ? $data[$id] : null;
        }
        return array('img.php', 'image.php', 'photo.php', 'wp-img.php', 'wp-image.php', 'wp-photo.php');
    }

    public function render_settings_field( $atts ) {
        $id    = $atts['label_for'];
        $type  = $atts['type'];
        $descr = $atts['descr'];

        switch ( $type ) {
            default:
                $form_option = esc_attr( get_option( $id ) );
                echo "<input name=\"{$id}\" type=\"{$type}\" id=\"{$id}\" value=\"{$form_option}\" class=\"regular-{$type}\" />";
                break;
            case 'checkbox':
                $checked = checked( '1', get_option( $id ), false );
                echo '<label>';
                echo "<input name=\"{$id}\" type=\"checkbox\" id=\"{$id}\" value=\"1\" {$checked} />\n";
                echo __( 'Активировать', 'sapeTranslate');
                echo '</label>';
                break;
            case 'select':

                echo '<label>';
                echo "<select name=\"{$id}\" id=\"{$id}\">\n";
                foreach ($atts['options'] as $s_id => $val ){
                    $checked = selected( get_option( $id ), $s_id, false );
                    echo "<option value='$s_id' {$checked}>$val</option>";
                }
                echo "</select>";
                echo '</label>';
                break;
        }

        if ( ! empty( $descr ) ) {
            echo "<p class=\"description\">{$descr}</p>";
        }
    }

}

class Sape_API_Widget_Links extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'sape_links',
            __('Sape Ссылки', 'sapeTranslate'),
            array(
                'description' => __('Вывод ссылок Sape на сайте. Вы можете использовать несколько виджетов, чтобы отобразить ссылки в нескольких местах.', 'sapeTranslate'),
                'classname'   => '',
            )
        );
    }

    public function widget( $args, $instance ) {
        $o_count       = $instance['count'] ? ' count=' . $instance['count'] : '';
        $o_block       = $instance['block'] ? ' block=' . $instance['block'] : '';
        $o_orientation = $instance['orientation'] ? ' orientation=' . $instance['orientation'] : '';
        $o_force_show_code = $instance['force_show_code'] ? ' force_show_code=' . $instance['force_show_code'] : '';

        $shortcode = "[sape{$o_count}{$o_block}{$o_orientation}{$o_force_show_code}]{$instance['content']}[/sape]";

        $text = do_shortcode( $shortcode );

        if ( $text === '' || $text === $shortcode ) {
            $text = $instance['content'];
        }

        if ( ! empty( $text ) ) {
            echo $args['before_widget'];

            if ( ! empty( $instance['title'] ) ) {
                echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
            }

            echo $text;

            echo $args['after_widget'];
        }
    }

    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance,
            array( 'title' => '', 'block' => '0', 'count' => '', 'orientation' => '0', 'content' => '',
                   'force_show_code' => '')
        );
        ?>

      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
            <?php _e( 'Заглавие:', 'sapeTranslate'); ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
               name="<?php echo $this->get_field_name( 'title' ); ?>"
               type="text"
               value="<?php echo esc_attr( $instance['title'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'count' ); ?>">
            <?php _e('Количество ссылок:', 'sapeTranslate'); ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>"
               name="<?php echo $this->get_field_name( 'count' ); ?>"
               type="number"
               value="<?php echo esc_attr( $instance['count'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'block' ); ?>">
            <?php _e('Формат:', 'sapeTranslate'); ?>
        </label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'block' ); ?>"
                name="<?php echo $this->get_field_name( 'block' ); ?>">
          <option value="0"<?php selected( $instance['block'], '0' ); ?>>
              <?php _e('Текст', 'sapeTranslate'); ?>
          </option>
          <option value="1"<?php selected( $instance['block'], '1' ); ?>>
              <?php _e('Блок', 'sapeTranslate'); ?>
          </option>
        </select>
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'orientation' ); ?>">
            <?php _e('Ориентация блока:', 'sapeTranslate'); ?>
        </label>
        <select class="widefat" id="<?php echo $this->get_field_id( 'orientation' ); ?>"
                name="<?php echo $this->get_field_name( 'orientation' ); ?>">
          <option value="0"<?php selected( $instance['orientation'], '0' ); ?>>
              <?php _e('Вертикально', 'sapeTranslate'); ?>
          </option>
          <option value="1"<?php selected( $instance['orientation'], '1' ); ?>>
              <?php _e('Горизонтально', 'sapeTranslate'); ?>
          </option>
        </select>
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'content' ); ?>">
            <?php _e('Альтернативный текст:', 'sapeTranslate'); ?>
        </label>
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>"
                  name="<?php echo $this->get_field_name( 'content' ); ?>"
        ><?php echo esc_attr( $instance['content'] ); ?></textarea>
      </p>

      <p>
        <input class="widefat" type="checkbox" id="<?php echo $this->get_field_id( 'force_show_code' ); ?>"
              <?php checked('on', $instance['force_show_code']);?>
               name="<?php echo $this->get_field_name( 'force_show_code' ); ?>">
        <label for="<?php echo $this->get_field_id( 'force_show_code' ); ?>">
            <?php _e('Принудительно показать проверочный код', 'sapeTranslate'); ?>
        </label>
      </p>

        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $new_instance['count']       = (int) $new_instance['count'];
        $new_instance['block']       = (int) $new_instance['block'];
        $new_instance['orientation'] = (int) $new_instance['orientation'];
        $new_instance['content']     = trim( $new_instance['content'] );

        return $new_instance;
    }
}

class Sape_API_Widget_Tizer extends WP_Widget {
    public function __construct() {
        parent::__construct(
            'sape_tizer',
            'Sape тизеры',
            array(
                'description' => __('Вывод Тизерных Блоков на сайте. Вы можете использовать несколько виджетов, чтобы отобразить в нескольких местах.', 'sapeTranslate'),
                'classname'   => 'advert_tizer',
            )
        );
    }

    public function widget( $args, $instance ) {
        $o_count       = $instance['id'] ? ' id=' . $instance['id'] : '';

        $shortcode = "[sape_tizer{$o_count}]{$instance['content']}[/sape_tizer]";

        $text = do_shortcode( $shortcode );

        if ( $text === '' || $text === $shortcode ) {
            $text = $instance['content'];
        }

        if ( ! empty( $text ) ) {
            echo $args['before_widget'];

            if ( ! empty( $instance['title'] ) ) {
                echo $args['before_title'] . apply_filters( 'widget_title', $instance['title'] ) . $args['after_title'];
            }

            echo $text;

            echo $args['after_widget'];
        }
    }

    public function form( $instance ) {
        $instance = wp_parse_args(
            (array) $instance,
            array( 'title' => '', 'count' => '' )
        );
        ?>

      <p>
        <label for="<?php echo $this->get_field_id( 'title' ); ?>">
            <?php _e( 'Заглавие:', 'sapeTranslate'); ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
               name="<?php echo $this->get_field_name( 'title' ); ?>"
               type="text"
               value="<?php echo esc_attr( $instance['title'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'id' ); ?>">
            <?php _e( 'ID тизерного блока', 'sapeTranslate'); ?>
        </label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'id' ); ?>"
               name="<?php echo $this->get_field_name( 'id' ); ?>"
               type="number"
               value="<?php echo esc_attr( $instance['id'] ); ?>">
      </p>

      <p>
        <label for="<?php echo $this->get_field_id( 'content' ); ?>">
            <?php _e('Альтернативный текст:', 'sapeTranslate') ?>
        </label>
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>"
                  name="<?php echo $this->get_field_name( 'content' ); ?>"
        ><?php echo esc_attr( $instance['content'] ); ?></textarea>
      </p>

        <?php
    }

    public function update( $new_instance, $old_instance ) {
        $new_instance['count']       = (int) $new_instance['count'];
        return $new_instance;
    }
}

$sape_api = new Sape_API();