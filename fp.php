<?php
/*
Plugin Name: FacePress
Description: All the tools you need to integrate your wordpress and facebook.
Author: Louy
Version: 1.4
Author URI: http://l0uy.com/
Text Domain: fp
Domain Path: /po
*/
/*
if you want to force the plugin to use app id and secret and/or fanpage,
 add your info and uncomment the following three lines:
*/
//define('FACEBOOK_APP_ID', 'EnterYourAppIDHere');
//define('FACEBOOK_APP_SECRET', 'EtnterYourSecretHere');
//define('FACEBOOK_FANPAGE', 'EnterYourPageIDHere');

//define('FACEBOOK_DISABLE_LOGIN', true);

// Load translations
load_plugin_textdomain( 'fp', false, dirname( plugin_basename( __FILE__ ) ) . '/po/' );

define('FP_VERSION', '1.4');

require_once dirname(__FILE__).'/wp-oauth.php';

/**
 * FacePress Core:
 */
function fp_activate(){
	oauth_activate();
	
	// require PHP 5
	if (version_compare(PHP_VERSION, '5.0.0', '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die(__("Sorry, FacePress requires PHP 5 or higher. Ask your host how to enable PHP 5 as the default on your servers.", 'fp'));
	}
}
register_activation_hook(__FILE__, 'fp_activate');

if( !isset( $_SERVER['HTTPS'] ) )
	$_SERVER['HTTPS'] = false;

add_action('init','fp_init');
function fp_init() {

	wp_enqueue_script('jquery');

	if (session_id() == '') {
		session_start();
	}
	
	isset($_SESSION['fb-connected']) or 
		$_SESSION['fb-connected'] = false;
	
}

function fp_app_options_defined() {
    return defined('FACEBOOK_APP_ID') && defined('FACEBOOK_APP_SECRET');
}

function fp_options($k=false) {
    $options = get_option('fp_options');

    if( !is_array($options) ) {
        add_option('fp_options', $options = array(
            'allow_comment' => false,
            'comm_text' => '',
            'like_position' => '',
            'like_layout' => '',
            'like_send' => 'true',
            'like_action' => '',
            'like_css' => '',
        ));
    }

    $options = array_merge($options, fp_app_options());
    if( $k ) {
            $options = $options[$k];
    }
    return $options;
}

function fp_app_options() {
    $options = get_site_option('fp_app_options');

    if( !is_array($options) ) {
        add_site_option('fp_app_options', $options = array(
            'appId' => '',
            'secret' => '',
            'fanpage' => '',
            'disable_login' => false
        ));
    }

    if( fp_app_options_defined() ) {
        defined('FACEBOOK_FANPAGE') or
		define('FACEBOOK_FANPAGE', '');
		
		defined('FACEBOOK_DISABLE_LOGIN') or
		define('FACEBOOK_DISABLE_LOGIN', fales);
		
        $options['appId']         = FACEBOOK_APP_ID       ;
        $options['secret']        = FACEBOOK_APP_SECRET   ;
        $options['fanpage']       = FACEBOOK_FANPAGE      ;
        $options['disable_login'] = FACEBOOK_DISABLE_LOGIN;
    }

    return $options;
}

function fp_ready() {
	$o = fp_app_options();
	return isset($o['appId'])  && !empty($o['appId']) &&
	       isset($o['secret']) && !empty($o['secret']);
}

function fb_access_token() {
	if( $_SESSION['fb-connected'] ) {
		return $_SESSION['fp_access_token'];
	}
	return false;
}

function fb_app_access_token() {
	if( fp_ready() ) {
		return fp_options('appId') . '|' . fp_options('secret');
	}
	return false;
}

function fb_me() {
	if( $_SESSION['fb-connected'] ) {
		return $_SESSION['fb-me'];
	}
	return false;
}

function fb_all_js() {
	/* translators: Facebook Locale */
	$locale = _x('en_US', 'FB Locale', 'fp');
?>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) {return;}
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/<?php echo $locale; ?>/all.js#xfbml=1&appId=<?php echo fp_options('appId'); ?>";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>
<?php
}

// basic XFBML load into footer
add_action('wp_footer','fb_all_js',20);

// fix up the html tag to have the FBML extensions
//add_filter('language_attributes','fp_lang_atts');
function fp_lang_atts($lang) {
    return ' xmlns:fb="http://www.facebook.com/2008/fbml" xmlns:og="http://opengraphprotocol.org/schema/" '.$lang;
}

function fp_get_connect_button($action='', $perms = '', $data = array(), $image ='login-with-fb.gif') {
	$image = apply_filters('fp_connect_button_image', $image, $action);
	$imgsrc = apply_filters('fp_connect_button_image_src', plugins_url() . '/fp/images/'.$image, $image, $action);
	$return = '<a href="' . oauth_link('facebook', array(
				'action' => $action,
				'location' => fp_get_current_url(),
				'perms' => $perms
				) ) . '" title="'.__('Login with Facebook', 'fp').'"';
				
	foreach( $data as $k => $v ) {
		$return .= " $k=\"$v\"";
	}
	
	$return .= '>'.
			'<img src="'.$imgsrc.'" alt="'.__('Login with Facebook', 'fp').'" style="border:none;" />'.
		'</a>';
	return apply_filters('fp_get_connect_button', $return, $action, $perms, $image, $data);
}

function fp_get_current_url() {
	// build the URL in the address bar
	$requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	$requested_url .= $_SERVER['HTTP_HOST'];
	$requested_url .= $_SERVER['REQUEST_URI'];
	return $requested_url;
}

// this adds the app id to allow you to use Facebook Insights on your domain, linked to your application.
add_action('wp_head','fp_meta_head');
function fp_meta_head() {
	$options = fp_options();
	
	if ($options['appId']) {
	?>
<meta property='fb:app_id' content='<?php echo $options['appId']; ?>' />
<?php
	}
	?>
<meta property="og:site_name" content="<?php bloginfo('name'); ?>" />
<?php
	if ( is_singular() ) {
		global $wp_the_query;
		if ( $id = $wp_the_query->get_queried_object_id() ) {
			$link = get_permalink( $id );
			echo "<meta property='og:url' content='{$link}' />\n";
		}
	} else if (is_home()) {
		$link = get_bloginfo('url');
		echo "<meta property='og:url' content='{$link}' />\n";
	}
	do_action('og_meta');
}

require_once 'fp-oauth.php';

require_once 'fp-login.php';

require_once 'fp-admin.php';

require_once 'fp-comment.php';

require_once 'fp-like.php';

