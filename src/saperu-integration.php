<?php
/*
Plugin Name: Sape.ru integration
Plugin URI: https://github.com/sape-ru/client-code-wordpress/releases
Description: Plugin for Sape.ru webmaster services integration
Version: 0.02
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

	public function __construct() {
		$this->_plugin_basename = plugin_basename( __FILE__ );
		// misc
		load_plugin_textdomain( 'sape-api', false, dirname( $this->_plugin_basename ) . '/languages' );
		register_activation_hook( __FILE__, array( __CLASS__, 'activation_hook' ) );
		register_deactivation_hook( __FILE__, array( __CLASS__, 'deactivation_hook' ) );
		register_uninstall_hook( __FILE__, array( __CLASS__, 'uninstall_hook' ) );

		// init
		add_action( 'init', array( &$this, 'init' ) );

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
		$this->_registerHeadScript();
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
		}
	}

	protected function _registerContext()
	{
		if ( get_option( 'sape_part_is_context' ) && _SAPE_USER !== '' ) {

			add_filter( 'the_content', array( &$this, '_sape_replace_in_text_segment' ), 11, 1 );
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

			if ( _SAPE_USER !== '' ) {
				add_shortcode('sape_tizer', array(&$this, 'shortcode_sape_tizer'));
				add_filter('no_texturize_shortcodes', function ($list) {
					$list[] = 'sape_tizer';

					return $list;
				});
			}
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

	protected function _registerHeadScript()
	{
		if(_SAPE_USER !== ''){
			if(get_option( 'sape_part_is_rtb' ) || get_option( 'sape_part_is_tizer' )){
				add_action( 'wp_head', array( &$this, 'render_remained_tizer' ), 1 );
			}
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

	public function render_remained_tizer() {
		//if ( $this->_getSapeClient()->_teasers_page > 0 ) {
			echo do_shortcode( '[sape_tizer]' );
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

	public static function activation_hook() {
		// init options
		foreach ( self::$_options as $option => $value ) {
			add_option( $option, $value );
		}

		// let make dir and copy sape's files to uploads/.sape/
		if ( ! wp_mkdir_p( self::_getSapePath() ) ) {
			$path = plugin_basename( __FILE__ );
			deactivate_plugins( $path );

			$path_upload = ABSPATH . WPINC . '/upload';
			$link        = wp_nonce_url( admin_url( 'plugins.php?action=activate&plugin=' . $path ), 'activate-plugin_' . $path );
			$string      = '';
			$string .= 'Sape: ' . sprintf( 'директория %s не доступна для записи', '<i>`' . $path_upload . '`</i>' ) . '.<br/>';
			$string .= sprintf( 'Исправьте и активируйте плагин %s заново', '<b>' . $path . '</b>' ) . '.<br/>';
			$string .= '<a href="' . $link . '" class="edit">' . __( 'Activate' ) . '</a>';

			wp_die( $string );
		} else {
			// let copy file to created dir
			$local_path = dirname( __FILE__ ) . DIRECTORY_SEPARATOR . 'sape';
			copy( $local_path . DIRECTORY_SEPARATOR . 'sape.php', self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php' );
			copy( $local_path . DIRECTORY_SEPARATOR . '.htaccess', self::_getSapePath() . DIRECTORY_SEPARATOR . '.htaccess' );
		}
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
		return $this->_getSapeClient()->return_counter();
	}

	private function _getSapeContext() {
		if ( $this->_sape_context === null ) {
			include_once self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
			$this->_sape_context = new SAPE_context( $this->_sape_options );
		}

		return $this->_sape_context;
	}
	public function _sape_replace_in_text_segment( $text ) {
		return $this->_getSapeContext()->replace_in_text_segment( $text );
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
	public function _sape_return_process_request() {
		return $this->_getSapeArticles()->process_request();
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
		if($SID){
			$file_name = $this->_getTizerImageOptions($args);
			if($file_name){
				$dir = self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
				$data = sprintf('<?php define(\'_SAPE_USER\', \'%s\');require_once(\'%s\');$sape = new SAPE_client(array(\'charset\' => \'UTF-8\'));$sape->show_image();', $SID, $dir);
				file_put_contents($_SERVER['DOCUMENT_ROOT'].'/'.$file_name, $data);
			}
		}
		return $args;
	}
	function change_field_article($args)
	{
		$SID = get_option('sape_user');
		if($SID){
				$dir = self::_getSapePath() . DIRECTORY_SEPARATOR . 'sape.php';
				$data = sprintf('<?php define(\'_SAPE_USER\', \'%s\');require_once(\'%s\');$sape = new SAPE_articles();echo $sape->process_request();', $SID, $dir);
				file_put_contents($_SERVER['DOCUMENT_ROOT'].'/'.$SID.'.php', $data);
		}
		return $args;
	}
	protected function _getTizerImageOptions($id = null)
	{
		if($id){
			$data = $this->_getTizerImageOptions();
			return isset($data[$id])?$data[$id]:null;
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