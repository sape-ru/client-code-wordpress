<?php
/*
Plugin Name: Sape.ru integration
Plugin URI: https://github.com/sape-ru/client-code-wordpress/releases
Description: Plugin for Sape.ru webmaster services integration
Version: 0.11
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
		'sape_part_is_articles' => true,
		'sape_part_is_tizer'    => false,
		'sape_part_is_rtb'    => false,
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

	/** @var SAPE_articles */
	private $_sape_articles;

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

		// _SAPE_USER
		if ( ! defined( '_SAPE_USER' ) ) {
			define( '_SAPE_USER', get_option( 'sape_user' ) );
		} else {
			if ( is_admin() ) {
				add_action( 'admin_init', function () {
					add_action( 'admin_notices', function () {
						echo '<div class="update-nag"><p>';
						echo sprintf( 'Константа %s уже определена ранее!', '<code>_SAPE_USER</code>' );
						echo ' ';
						echo sprintf( 'Настройки плагина %s не применены!', '<code>Sape.ru integration</code>' );
						echo '</p></div>';
					} );
				} );
			}
		}

		$this->_registerLinks();
		$this->_registerContext();
		$this->_registerArticles();
		$this->_registerTizer();
		$this->_registerRTB();
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

	protected function _registerArticles()
	{
		if ( get_option( 'sape_part_is_articles' ) && _SAPE_USER !== '' ) {

			add_action( 'widgets_init', function () {
				register_widget( 'Sape_API_Widget_Articles' );
			}, 1 );

			add_shortcode('sape_article', array(&$this, 'shortcode_sape_article'));
			add_filter('no_texturize_shortcodes', function ($list) {
				$list[] = 'sape_article';

				return $list;
			});

			add_action( 'wp_footer', array( &$this, 'render_remained_article' ), 1 );

			// Выводим контент постов без внутреннего преобразования Wordpress
            add_filter( 'the_content', array(&$this, 'disable_transform_content') );
		}
	}

	protected function _registerContext()
	{
		if ( get_option( 'sape_part_is_context' ) && _SAPE_USER !== '' ) {
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
		if ( get_option( 'sape_part_is_tizer' ) && _SAPE_USER !== '' ) {

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

	protected function _registerRTB()
	{
		if ( get_option( 'sape_part_is_rtb' ) && _SAPE_USER !== '' ) {
			add_action( 'widgets_init', function () {register_widget( 'Sape_API_Widget_RTB' );}, 1 );
		}
	}

	protected function _registerCounter()
	{
		if ( _SAPE_USER !== '' ) {
			add_action( 'wp_footer', array( &$this, '_sape_return_counter' ), 1 );
		}
	}

	public function render_remained_links() {
		//if ( $this->_getSapeClient()->_links_page > 0 ) {
			echo do_shortcode( '[sape block=1 orientation=1]' );
		//}
	}

	public function render_remained_article() {
		//if ( $this->_getSapeArticles()->_links_page > 0 ) {
			echo do_shortcode( '[sape_article]' );
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

		// deny edit and delete sape article posts
        add_action('user_has_cap', array( &$this, 'deny_edit_and_delete_posts' ), 10, 3);
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

    function deny_edit_and_delete_posts($allcaps, $cap, $args) {
        if (in_array($args[0], array('edit_post', 'delete_post'))) {
            $postId = (int)$args[2];
            if ((int)$postId > 0) {
                $sape_articles_post_ids = $this->_getSapeArticles()->wp_get_post_ids();
                if (in_array($postId, $sape_articles_post_ids)) {
                    $allcaps[$cap[0]] = false;
                }
            }
        }
        return $allcaps;
    }

    function disable_transform_content($content) {
	    try {
            $postId = $GLOBALS['post']->ID;
            if ((int)$postId > 0) {
                $sape_articles_post_ids = $this->_getSapeArticles()->wp_get_post_ids();
                if (in_array($postId, $sape_articles_post_ids)) {
                    remove_all_filters('the_content');
                    $content = $GLOBALS['post']->post_content;
                }
            }
        } catch (Exception $e) {}

        return $content;
    }

	public static function activation_hook() {
		// init options
		foreach ( self::$_options as $option => $value ) {
			add_option( $option, $value );
		}

		// let make dir and copy sape's files to uploads/.sape/
		if ( ! wp_mkdir_p( self::_getSapePath() ) ) {
            $activationFailedMessage = 'Sape: ' . sprintf( 'директория %s не доступна для записи', '<i>`' . ABSPATH . WPINC . '/upload' . '`</i>' );
		    self::chmod_wrong_on_activation($activationFailedMessage);
		}

        // let copy file to created dir
        $local_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'sape';

        $files = array(
            $local_path . DIRECTORY_SEPARATOR . 'sape.php' => self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php',
            $local_path . DIRECTORY_SEPARATOR . '.htaccess' => self::_getSapePath() . DIRECTORY_SEPARATOR . '.htaccess'
        );

        foreach ($files as $filePathFrom => $filePathTo) {
            if (!copy( $filePathFrom, $filePathTo)) {
                $activationFailedMessage = 'Sape: ' . sprintf( 'файл %s не доступен для записи', '<i>`' . $filePathTo . '`</i>');
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
        $string .= sprintf( 'Исправьте и активируйте плагин %s заново', '<b>' . $path . '</b>' ) . '.<br/>';
        $string .= '<a href="' . $link . '" class="edit">' . __( 'Activate' ) . '</a>';

        wp_die( $string );
    }

    public static function chmod_wrong_on_save_options($saveFailedMessage) {
        $string      = '';
        $string .= $saveFailedMessage . '<br/>';
        $string .= 'или исправить права доступа и <a href="admin.php?page=page_sape">настроить</a> плагин заново.';

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
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_client = new SAPE_client( $this->_sape_options );
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
	    if (get_option('sape_part_is_articles')) {
            $counterHtml = $this->_getSapeArticles()->return_counter();
        }
        if ($counterHtml == '') {
            $counterHtml = $this->_getSapeClient()->return_counter();
        }
        echo $counterHtml;
	}

	private function _getSapeContext() {
		if ( $this->_sape_context === null ) {
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_context = new SAPE_context( $this->_sape_options );
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

	private function _getSapeArticles() {
		if ( $this->_sape_articles === null ) {
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_articles = new SAPE_articles( $this->_sape_options );
		}

		return $this->_sape_articles;
	}

	public function _sape_return_announcements( $n ) {
		return $this->_getSapeArticles()->return_announcements( $n );
	}

	public function _sape_wp_process() {
	    $newArticles     = array();
        $updateArticles  = array();
        $deleteArticles  = array();

        $uploadDirInfo = wp_upload_dir();

        $this->_getSapeArticles()->wp_process($newArticles, $updateArticles, $deleteArticles, $uploadDirInfo['basedir']);

	    // Блок обработки новых статей
	    if (isset($updateArticles) && is_array($updateArticles) && count($newArticles) > 0) {
            $addedPosts = array();
            foreach ($newArticles as $articleId => $articleInfo) {
                // Добавляем служебный тег
                $args = array(
                    'sape_article_id'  => $articleId,
                    'post_title'       => $articleInfo['title'],
                    'post_content'     => $articleInfo['body'],
                    'post_status'      => 'publish'
                );

                // Защита от дублирования статей
                global $wpdb;
                $postInfo = $wpdb->get_row(
                    "
                        SELECT 
                            id
                        FROM 
                            $wpdb->posts 
                        WHERE
                            post_name = '" . (int)$articleId . "' AND 
                            post_status = 'publish'
                        ORDER BY 
                            id DESC
                    ", 'ARRAY_A'
                );

                if (count($postInfo) > 0) {
                    $args['post_id'] = (int)$postInfo['id'];
                }
                unset($postInfo);

                // Создаем пост в WordPress
                $postInfo = $this->addOrUpdatePost($args);
                $addedPosts[$articleId] = $postInfo;

                // Прописываем meta-теги
                add_post_meta($postInfo['wp_post_id'], 'sseo_meta_title', $articleInfo['title']);
                add_post_meta($postInfo['wp_post_id'], 'sseo_meta_keywords', $articleInfo['keywords']);
                add_post_meta($postInfo['wp_post_id'], 'sseo_meta_description', $articleInfo['description']);
	        }

            // Пушим в диспенсер УРЛы
            $this->_getSapeArticles()->wp_push_posts($addedPosts, $uploadDirInfo['baseurl']);

            // Сохраняем изменения в локальный файл
            try {
                $this->_getSapeArticles()->wp_save_local_db($addedPosts, 'add');
            } catch (Exception $e) {}
        }

        // Блок обработки существующих статей
        if (isset($updateArticles) && is_array($updateArticles) && count($updateArticles) > 0) {
            $updatedPosts = array();
            foreach ($updateArticles as $articleId => $articleInfo) {
                $args = array(
                    'sape_article_id'  => $articleId,
                    'post_id'          => $articleInfo['wp_post_id'],
                    'post_title'       => $articleInfo['title'],
                    'post_content'     => $articleInfo['body'],
                    'post_status'      => 'publish'
                );

                // Обновляем пост в WordPress
                $postInfo = $this->addOrUpdatePost($args);
                $updatedPosts[$articleId] = $postInfo;

                // Прописываем meta-теги
                update_post_meta($postInfo['wp_post_id'], 'sseo_meta_title', $articleInfo['title']);
                update_post_meta($postInfo['wp_post_id'], 'sseo_meta_keywords', $articleInfo['keywords']);
                update_post_meta($postInfo['wp_post_id'], 'sseo_meta_description', $articleInfo['description']);

                // Сохраняем изменения в локальный файл
                $this->_getSapeArticles()->wp_save_local_db($updatedPosts, 'update');
            }
        }

        // Блок обработки удаления статей
        if (isset($deleteArticles) && is_array($deleteArticles) && count($deleteArticles) > 0) {
            $deletedPosts = array();
            foreach ($deleteArticles as $articleId => $articleInfo) {
                if (isset($articleInfo['wp_post_id']) && (int)$articleInfo['wp_post_id'] > 0) {
                    // Удаляем пост в WordPress
                    $this->deletePost($articleInfo['wp_post_id']);
                    $deletedPosts[$articleId] = array('wp_post_id' => (int)$articleInfo['wp_post_id']);
                }
            }

            // Сохраняем изменения в локальный файл
            $this->_getSapeArticles()->wp_save_local_db($deletedPosts, 'delete');
        }
    }

	public function shortcode_sape( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'count'       => null,
			'block'       => 0,
			'orientation' => 0
		), $atts );

		$text = $this->_sape_return_links(
			$atts['count'],
			array(
				'as_block'          => $atts['block'] == 1 ? true : false,
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

	public function shortcode_sape_article( $atts, $content = null ) {
		$atts = shortcode_atts( array(
			'count'       => null,
		), $atts );

		$text = $this->_sape_return_announcements(
			$atts['count'],
			array(
			)
		);

		// Запускаем обработку размещения статей
		$this->_sape_wp_process();

		return ! empty( $text ) ? $text : $content;
	}

	public function plugin_action_links( $links ) {
		unset( $links['edit'] );
		$settings_link = '<a href="admin.php?page=page_sape">' . __( 'Settings' ) . '</a>';
		array_unshift( $links, $settings_link );

		return $links;
	}

	public function plugin_row_meta( $links, $file ) {
		if ( $file == $this->_plugin_basename ) {
			$settings_link = '<a href="admin.php?page=page_sape">' . __( 'Settings' ) . '</a>';
			$links[]       = $settings_link;
			$links[]       = 'Code is poetry!';
		}

		return $links;
	}

	public function admin_menu() {
		add_menu_page(
			'Sape ' . __( 'Settings' ), // title
			'Sape API', // menu title
			'manage_options', // capability
			'page_sape', // menu slug
			array( &$this, 'page_sape' ), // callback
			'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABAAAAAQCAYAAAAf8/9hAAAAa0lEQVQ4T2OUqlr7n4ECwEg1A562BmG4Q7p6HVwMXR4mB3cBSAGyBmTT0OWQ+SgGoDsBZiBRBqBrRtaEz3u0cwGxMUufaCQ6DNDjHVcsIHsPZzrAFwvIFpEVC0S5AD0l4kpk1IsFYuMdXR0AYDBvEZHcuRUAAAAASUVORK5CYII='
		);

		add_submenu_page(
			'page_sape',
			'Sape ' . __( 'Settings' ), // title
			__( 'Settings' ), // menu title
			'manage_options', // capability
			'page_sape', // menu slug
			array( &$this, 'page_sape' ) // callback
		);
	}

	public function page_sape() {
		?>
		<div class="wrap">

			<h1>Sape API</h1>

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
        register_setting( 'sape_base', 'sape_part_is_articles', array('type'=>'boolval' , 'sanitize_callback' => array( &$this, 'change_field_article')) );

        register_setting( 'sape_base', 'sape_part_is_articles_post_author', array('type'=>'intval'));
        register_setting( 'sape_base', 'sape_part_is_articles_post_category', array('type'=>'intval'));

		register_setting( 'sape_base', 'sape_part_is_tizer', 'boolval' );
		register_setting( 'sape_base', 'sape_part_is_tizer_image', array('type'=>'intval', 'sanitize_callback' => array( &$this, 'change_field_tizer_image')) );
		register_setting( 'sape_base', 'sape_part_is_rtb', 'boolval' );
		register_setting( 'sape_base', 'sape_widget_class', 'trim' );

		// add sections
		add_settings_section(
			'section__sape_identification', // id
			'Идентификационная часть', // title
			function () {
				echo 'Нет необходимости скачивать файлы и архивы или устанавливать что-либо вручную.';
				echo '<br/>';
				echo 'Плагин всё сделает автоматически. Просто заполните настройки ниже.';
			}, // callback
			'page_sape' // page
		);

		add_settings_section(
			'section__sape_parts', // id
			'Системы монетизации', // title
			function () {
				echo 'Укажите ниже какие системы заработка активировать.';
				echo '<br/>';
				echo sprintf( 'Плагин отлично работает с фильтром %s.', '<code>wptexturize</code>' );
				echo '<br/>';
				echo sprintf( 'Если вы выведите не все проданные ссылки на странице, то оставшиеся добавятся в футер (подвал) сайта во избежание появления у ссылок статуса %s.', '<code>ERROR</code>' );
			}, // callback
			'page_sape' // page
		);

		// add fields
		add_settings_field(
			'sape_user', // id
			'_SAPE_USER', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_identification', // section
			array(
				'label_for' => 'sape_user',
				'type'      => 'text',
				'descr'     => '
Это ваш уникальный идентификатор (хеш).<br/>
Можете найти его на сайте
<a target="_blank" href="//www.sape.ru/">sape.ru</a> (реф)
кликнув по кнопке <b>"добавить площадку"</b>.<br/>
Будет похож на что-то вроде <b>d12d0d074c7ba7f6f78d60e2bb560e3f</b>.',
			) // args
		);

		add_settings_field(
			'sape_part_is_client', // id
			'Простые ссылки', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_client',
				'type'      => 'checkbox',
				'descr'     => '
Текстовые и блочные ссылки.<br/>
После активации будет доступен как <a target="_blank" href="' . admin_url( 'widgets.php' ) . '">виджет</a> для вывода ссылок, так и шорткод:<br/>
<code>[sape]</code> -- вывод всех ссылок в формате текста<br/>
<code>[sape count=2]</code> -- вывод лишь двух ссылок<br/>
<code>[sape count=2 block=1]</code> -- вывод ссылок в формате блока<br/>
<code>[sape count=2 block=1 orientation=1]</code> -- вывод ссылок в формате блока горизонтально<br/>
<code>[sape]код другой биржи, html, js[/sape]</code> -- вывод альтернативного текста при отсутствии ссылок.<br/>
Для вывода внутри темы (шаблона) используйте следующий код: <code>' . esc_attr( '<?php echo do_shortcode(\'[sape]\') ?>' ) . '</code>',
			) // args
		);

		add_settings_field(
			'sape_part_is_context', // id
			'Контекстные ссылки', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_context',
				'type'      => 'checkbox',
				'descr'     => 'Ссылки внутри записей.',
			) // args
		);

        add_settings_field(
            'sape_part_is_articles', // id
            'Размещение статей', // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_articles',
                'type'      => 'checkbox',
                'descr'     => 'Вывод статей Sape с анонсами на сайте.',
            ) // args
        );

        add_settings_field(
            'sape_part_is_articles_post_author', // id
            '', // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_articles_post_author',
                'type'      => 'select',
                'descr'     => 'Пользователь, от имени которого будут создаваться статьи',
                'options' => $this-> _getArticleWpUsersOptions()
            ) // args
        );

        add_settings_field(
            'sape_part_is_articles_post_category', // id
            '', // title
            array( &$this, 'render_settings_field' ), // callback
            'page_sape', // page
            'section__sape_parts', // section
            array(
                'label_for' => 'sape_part_is_articles_post_category',
                'type'      => 'select',
                'descr'     => 'Рубрика, в которой будут создаваться статьи',
                'options' => $this-> _getArticleWpСategoryOptions()
            ) // args
        );

		add_settings_field(
			'sape_part_is_tizer', // id
			'Размещение тизеров', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_tizer',
				'type'      => 'checkbox',
				'descr'     => '
Тизерные блоки.<br/>
После активации будет доступен как <a target="_blank" href="' . admin_url( 'widgets.php' ) . '">виджет</a> для вывода тизерных блоков, так и шорткод:<br/>
<code>[sape_tizer id=1]</code> -- вывод тизерного блока, с ID 1<br/>
<code>[sape_tizer]код другой биржи, html, js[/sape_tizer]</code> -- вывод альтернативного текста при отсутствии тизерного блока.<br/>
Для вывода внутри темы (шаблона) используйте следующий код: <code>' . esc_attr( '<?php echo do_shortcode(\'[sape_tizer id=ID_БЛОКА]\') ?>' ) . '</code>',
			) // args
		);

		add_settings_field(
			'sape_part_is_tizer_image', // id
			'Файл изображения тизеров', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_tizer_image',
				'type'      => 'select',
				'descr'     => 'Имя файла, показывающего картинки тизеров',
				'options' => $this-> _getTizerImageOptions()
			) // args
		);

		add_settings_field(
			'sape_part_is_rtb', // id
			'Размещение RTB блоков', // title
			array( &$this, 'render_settings_field' ), // callback
			'page_sape', // page
			'section__sape_parts', // section
			array(
				'label_for' => 'sape_part_is_rtb',
				'type'      => 'checkbox',
				'descr'     => '
RTB блоки.<br/>
После активации будет доступен как <a target="_blank" href="' . admin_url( 'widgets.php' ) . '">виджет</a> для вывода RTB блоков
Для вывода внутри темы (шаблона) используйте следующий код, полученные в RTB.SAPE',
			) // args
		);
	}

	function change_field_tizer_image($args)
	{
	    $SID = get_option('sape_user');

	    if ($SID) {
	        $file_name = $this->_getTizerImageOptions($args);
            if(isset($file_name) && !is_array($file_name) && $file_name <> '') {
				$dir = self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
				$data = sprintf('<?php define(\'_SAPE_USER\', \'%s\');require_once(\'%s\');$sape = new SAPE_client(array(\'charset\' => \'UTF-8\'));$sape->show_image();', $SID, $dir);
				$fileName = $_SERVER['DOCUMENT_ROOT'].'/'.$file_name;
                if (!file_put_contents($fileName, $data)) {
                    $userData = file_get_contents($fileName);
                    if ($userData !== $data) {
                        $message = 'Sape: ' . sprintf( 'папка %s не доступна для записи.', '<i>`' . $_SERVER['DOCUMENT_ROOT'] . '`</i>');
                        $message .= '<p>Вы можете создать файл <i>`' . $fileName . '`</i> вручную с содержимым:</p>';
                        $message .= '<p>' . htmlentities($data) . '</p>';
                        self::chmod_wrong_on_save_options($message);
                    }
                }
			}
		}

		return $args;
	}

    function change_field_article($args)
    {
        $SID = get_option('sape_user');

        if ($SID) {
            $dir = self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
            $data = sprintf('<?php define(\'_SAPE_USER\', \'%s\');require_once(\'%s\');$sape = new SAPE_articles();echo $sape->process_request();', $SID, $dir);
            $fileName = $_SERVER['DOCUMENT_ROOT'].'/'.$SID.'.php';
            if (!file_put_contents($fileName, $data)) {
                $userData = file_get_contents($fileName);
                if ($userData !== $data) {
                    $message = 'Sape: ' . sprintf( 'папка %s не доступна для записи.', '<i>`' . $_SERVER['DOCUMENT_ROOT'] . '`</i>');
                    $message .= '<p>Вы можете создать файл <i>`' . $fileName . '`</i> вручную с содержимым:</p>';
                    $message .= '<p>' . htmlentities($data) . '</p>';
                    self::chmod_wrong_on_save_options($message);
                }
            }
        }

        return $args;
    }

    function addOrUpdatePost($args)
    {
        // Создаем массив данных новой записи
        $post_data = array(
            'post_title'    => wp_strip_all_tags( $args['post_title'] ),
            'post_content'  => $args['post_content'],
            'post_status'   => $args['post_status'],
            'post_author'   => get_option('sape_part_is_articles_post_author'),
            'post_category' => array(get_option('sape_part_is_articles_post_category')),
            'post_name'     => (int)$args['sape_article_id']
        );

        if (isset($args['post_id']) && (int)$args['post_id'] > 0) {
            $post_data['ID'] = (int)$args['post_id'];
        }

        // Вставляем или обновляем запись в БД
        $post_id = wp_insert_post( $post_data );

        // Получаем URL поста
        $post_url = get_permalink( $post_id );

        $result = array(
            'wp_post_id'          => $post_id,
            'wp_post_url'         => $post_url,
            'wp_post_title'       => wp_strip_all_tags( $args['post_title'] ),
            'wp_post_content'     => $args['post_content'],
            'wp_post_keywords'    => $args['post_keywords'],
            'wp_post_description' => $args['post_description'],
            'wp_post_status'      => $args['post_status']
        );

        return $result;
    }

    function deletePost($post_id)
    {
        return wp_delete_post( $post_id );
    }

	protected function _getTizerImageOptions($id = null)
	{
		if(isset($id)) {
			$data = $this->_getTizerImageOptions();
			return isset($data[$id]) ? $data[$id] : null;
		}
		return array('img.php', 'image.php', 'photo.php', 'wp-img.php', 'wp-image.php', 'wp-photo.php');
	}

    protected function _getArticleWpUsersOptions($id = null)
    {
        if($id){
            $data = $this->_getArticleWpUsersOptions();
            return isset($data[$id]) ? $data[$id] : null;
        }

        $wpUsers = get_users();
        $result = array();

        /* @var WP_User $wpUser */
        foreach ($wpUsers as $wpUser) {
            $result[$wpUser->ID] = $wpUser->display_name;
        }

        return $result;
    }

    protected function _getArticleWpСategoryOptions($id = null)
    {
        if($id){
            $data = $this->_getArticleWpСategoryOptions();
            return isset($data[$id]) ? $data[$id] : null;
        }

        $wpСategories = get_categories( array(
            'taxonomy'     => 'category',
            'type'         => 'post',
            'child_of'     => 0,
            'parent'       => '',
            'orderby'      => 'name',
            'order'        => 'ASC',
            'hide_empty'   => 0,
            'hierarchical' => 1,
            'exclude'      => '',
            'include'      => '',
            'number'       => 0,
            'pad_counts'   => false
        ) );

        $result = array();

        foreach ($wpСategories as $wpСategory) {
            $result[$wpСategory->term_id] = $wpСategory->cat_name;
        }

        return $result;
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
				echo __( 'Activate' );
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
			'Sape Ссылки',
			array(
				'description' => 'Вывод ссылок Sape на сайте. Вы можете использовать несколько виджетов, чтобы отобразить ссылки в нескольких местах.',
				'classname'   => '',
			)
		);
	}

	public function widget( $args, $instance ) {
		$o_count       = $instance['count'] ? ' count=' . $instance['count'] : '';
		$o_block       = $instance['block'] ? ' block=' . $instance['block'] : '';
		$o_orientation = $instance['orientation'] ? ' orientation=' . $instance['orientation'] : '';

		$shortcode = "[sape{$o_count}{$o_block}{$o_orientation}]{$instance['content']}[/sape]";

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
			array( 'title' => '', 'block' => '0', 'count' => '', 'orientation' => '0', 'content' => '' )
		);
		?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>">
				<?php _e( 'Title:' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
			       name="<?php echo $this->get_field_name( 'title' ); ?>"
			       type="text"
			       value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>">
				<?php echo 'Количество ссылок:'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>"
			       name="<?php echo $this->get_field_name( 'count' ); ?>"
			       type="number"
			       value="<?php echo esc_attr( $instance['count'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'block' ); ?>">
				<?php echo 'Формат:'; ?>
			</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'block' ); ?>"
			        name="<?php echo $this->get_field_name( 'block' ); ?>">
				<option value="0"<?php selected( $instance['block'], '0' ); ?>>
					<?php echo 'Текст'; ?>
				</option>
				<option value="1"<?php selected( $instance['block'], '1' ); ?>>
					<?php echo 'Блок'; ?>
				</option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'orientation' ); ?>">
				<?php echo 'Ориентация блока:'; ?>
			</label>
			<select class="widefat" id="<?php echo $this->get_field_id( 'orientation' ); ?>"
			        name="<?php echo $this->get_field_name( 'orientation' ); ?>">
				<option value="0"<?php selected( $instance['orientation'], '0' ); ?>>
					<?php echo 'Вертикально'; ?>
				</option>
				<option value="1"<?php selected( $instance['orientation'], '1' ); ?>>
					<?php echo 'Горизонтально'; ?>
				</option>
			</select>
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'content' ); ?>">
				<?php echo 'Альтернативный текст:'; ?>
			</label>
			<textarea class="widefat" id="<?php echo $this->get_field_id( 'content' ); ?>"
			          name="<?php echo $this->get_field_name( 'content' ); ?>"
			><?php echo esc_attr( $instance['content'] ); ?></textarea>
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

class Sape_API_Widget_Articles extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'sape_article',
			'Sape Articles',
			array(
				'description' => 'Вывод анонсов статрей Sape на сайте. Вы можете использовать несколько виджетов, чтобы отобразить анонсы в нескольких местах.',
				'classname'   => '',
			)
		);
	}

	public function widget( $args, $instance ) {
		$o_count       = $instance['count'] ? ' count=' . $instance['count'] : '';

		$shortcode = "[sape_article{$o_count}]{$instance['content']}[/sape_article]";

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
				<?php _e( 'Title:' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>"
				   type="text"
				   value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'count' ); ?>">
				<?php echo 'Количество анонсов:'; ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'count' ); ?>"
				   name="<?php echo $this->get_field_name( 'count' ); ?>"
				   type="number"
				   value="<?php echo esc_attr( $instance['count'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'content' ); ?>">
				<?php echo 'Альтернативный текст:'; ?>
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

class Sape_API_Widget_Tizer extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'sape_tizer',
			'Sape тизеры',
			array(
				'description' => 'Вывод тизеров блоков Sape на сайте. Вы можете использовать несколько виджетов, чтобы отобразить в нескольких местах.',
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
				<?php _e( 'Title:' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>"
				   type="text"
				   value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'id' ); ?>">
				<?php echo ( 'ID тизерного блока'); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'id' ); ?>"
				   name="<?php echo $this->get_field_name( 'id' ); ?>"
				   type="number"
				   value="<?php echo esc_attr( $instance['id'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'content' ); ?>">
				<?php echo 'Альтернативный текст:' ?>
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

class Sape_API_Widget_RTB extends WP_Widget {
	public function __construct() {
		parent::__construct(
			'sape_rtb',
			'Sape RTB',
			array(
				'description' => 'Вывод RTB блоков Sape на сайте. Вы можете использовать несколько виджетов, чтобы отобразить в нескольких местах.',
				'classname'   => 'advert_rtb',
			)
		);
	}

	public function widget( $args, $instance ) {

		$text = $instance['html'];

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
				<?php _e( 'Title:' ); ?>
			</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
				   name="<?php echo $this->get_field_name( 'title' ); ?>"
				   type="text"
				   value="<?php echo esc_attr( $instance['title'] ); ?>">
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'html' ); ?>">
				<?php echo ( 'Код RTB блока'); ?>
			</label>
			<textarea class="widefat" id="<?php echo $this->get_field_id( 'html' ); ?>"
				   name="<?php echo $this->get_field_name( 'html' ); ?>"
					  rows="3"
				   ><?php echo esc_attr( $instance['html'] ); ?></textarea>
		</p>


		<?php
	}

	public function update( $new_instance, $old_instance ) {
		$new_instance['count']       = (int) $new_instance['count'];
		return $new_instance;
	}
}

$sape_api = new Sape_API();