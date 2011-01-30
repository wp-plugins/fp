<?php
/*
Plugin Name: FacePress
Description: All the tools you need to integrate your wordpress and facebook.
Author: Louy
Version: 1.0
Author URI: http://l0uy.com/
Text Domain: fp
Domain Path: /po
*/
/*
if you want to force the plugin to use an app id, key, secret and/or fanpage,
 add your keys and uncomment the following three lines:
*/
//define('FACEBOOK_APP_ID', 'EnterYourAppIDHere');
//define('FACEBOOK_APP_KEY', 'EnterYourKeyHere');
//define('FACEBOOK_APP_SECRET', 'EtnterYourSecretHere');
//define('FACEBOOK_FANPAGE', 'EnterYourPageIDHere');

// Load translations
load_plugin_textdomain( 'fp', false, dirname( plugin_basename( __FILE__ ) ) . '/po/' );

define('FP_VERSION', '1.0');

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
    return defined('FACEBOOK_APP_ID') && defined('FACEBOOK_APP_KEY') && defined('FACEBOOK_APP_SECRET');
}

function fp_options($k=false) {
    $options = get_option('fp_options');

    if( !is_array($options) ) {
        add_option('fp_options', $options = array(
            'allow_comment' => false,
            'comm_text' => '',
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
            'key' => '',
            'secret' => '',
            'fanpage' => ''
        ));
    }

    if( fp_app_options_defined() ) {
        $options['appId']   = FACEBOOK_APP_ID    ;
        $options['key']     = FACEBOOK_APP_KEY   ;
        $options['secret']  = FACEBOOK_APP_SECRET;
        $options['fanpage'] = FACEBOOK_FANPAGE   ;
    }

    return $options;
}

function fp_ready() {
	$o = fp_app_options();
	return isset($o['appId'])  && !empty($o['appId']) &&
	       isset($o['key'])    && !empty($o['key'])   &&
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
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId  : '<?php echo fp_options('api_key'); ?>',
      status : false, cookie : true, xfbml  : true
    });
  };

  (function() {
    var e = document.createElement('script');
    e.src = document.location.protocol + '//connect.facebook.net/<?php echo $locale; ?>/all.js';
    e.async = true;
    document.getElementById('fb-root').appendChild(e);
  }());
</script>
<?php
}

// basic XFBML load into footer
add_action('wp_footer','fb_all_js',20);

// fix up the html tag to have the FBML extensions
add_filter('language_attributes','fp_lang_atts');
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
				) ) . '" title="'.__('Login with Facebook', 'tp').'"';
				
	foreach( $data as $k => $v ) {
		$return .= " $k=\"$v\"";
	}
	
	$return .= '>'.
			'<img src="'.$imgsrc.'" alt="'.__('Login with Facebook', 'tp').'" style="border:none;" />'.
		'</a>';
	return apply_filters('fp_get_connect_button', $return, $action, $perms, $image);
}

function fp_get_current_url() {
	// build the URL in the address bar
	$requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	$requested_url .= $_SERVER['HTTP_HOST'];
	$requested_url .= $_SERVER['REQUEST_URI'];
	return $requested_url;
}

function fp_get_login_button($text = '', $perms = '', $onlogin = '', $data = array() ) {
	$return = '<fb:login-button v="2" perms="'.$perms.'" onlogin="'.$onlogin.'"';
	foreach( $data as $k => $v ) {
		$return .= " $k=\"$v\"";
	}
	$return .= '>' . $text . '</fb:login-button>';
	return apply_filters('fp_get_login_button', $return, $text, $perms, $onlogin, $data );
}

// this adds the app id to allow you to use Facebook Insights on your domain, linked to your application.
add_action('wp_head','fp_meta_head');
function fp_meta_head() {
	$options = fp_options();
	
	if ($options['appid']) {
	?>
<meta property='fb:app_id' content='<?php echo $options['appid']; ?>' />
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


