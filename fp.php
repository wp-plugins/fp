<?php
/*
Plugin Name: FacePress
Description: All the tools you need to integrate your wordpress and facebook.
Author: Louy
Version: 0.1
Author URI: http://louyblog.wordpress.com 
Text Domain: tp
Domain Path: /po
*/
/*
if you want to force the plugin to use an app id, key, secret and/or fanpage, add your keys and uncomment the following three lines:
*/
//define('FACEBOOK_APP_ID', 'EnterYourAppIDHere');
//define('FACEBOOK_APP_KEY', 'EnterYourKeyHere');
//define('FACEBOOK_APP_SECRET', 'EnterYourSecretHere');
//define('FACEBOOK_APP_FANPAGE', 'EnterYourPageIDHere');

// Load translations
load_plugin_textdomain( 'fp', false, dirname( plugin_basename( __FILE__ ) ) . '/po/' );

/**
 * FacePress Core:
 */
define('FP_VERSION', '0.1');

// require PHP 5
function fp_activation_check(){
	if (version_compare(PHP_VERSION, '5.0.0', '<')) {
		deactivate_plugins(basename(__FILE__)); // Deactivate ourself
		wp_die("Sorry, FacePress requires PHP 5 or higher. Ask your host how to enable PHP 5 as the default on your servers.");
	}
}
register_activation_hook(__FILE__, 'fp_activation_check');

if( !isset( $_SERVER['HTTPS'] ) )
	$_SERVER['HTTPS'] = false;

function fp_options($k=false) {
	$options = get_option('fp_options');
	if( $k ) {
		$options = $options[$k];
	}
	return $options;
}

add_action('init','fp_init');
function fp_init() {

	if (session_id() == '') {
		session_start();
	}
	
	isset($_SESSION['fb-connected']) or 
		$_SESSION['fb-connected'] = false;
	
}

function fb_all_js() {
	/* translators: Facebook Locale */
	$locale = _x('en_US', 'FB Locale');
?>
<div id="fb-root"></div>
<script>
  window.fbAsyncInit = function() {
    FB.init({
      appId  : '<?php echo fp_options('api_key'); ?>',
      status : false, // check login status
      cookie : true, // enable cookies to allow the server to access the session
      xfbml  : true  // parse XFBML
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

// load the FB script into the head 
function fp_featureloader() {
	if ($_SERVER['HTTPS'] == 'on')
		echo "<script src='https://ssl.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php'></script>";
	else
		echo "<script src='http://static.ak.connect.facebook.com/js/api_lib/v0.4/FeatureLoader.js.php'></script>";
}

// fix up the html tag to have the FBML extensions
add_filter('language_attributes','fp_lang_atts');
function fp_lang_atts($lang) {
    return ' xmlns:fb="http://www.facebook.com/2008/fbml" xmlns:og="http://opengraphprotocol.org/schema/" '.$lang;
}

// basic XFBML load into footer
add_action('wp_footer','fp_add_base_js',20);
function fp_add_base_js() {
	fb_all_js();
	//fp_load_api(fp_options('api_key'));
};

function fp_load_api($key) {
$reload = apply_filters('fp_reload_state_change',false);

$sets['permsToRequestOnConnect']='email';
if ($reload) $sets['reloadIfSessionStateChanged'] = true;
?>
<script type="text/javascript">
FB.init({appId: '<?php echo $key; ?>', status: true, cookie: true, xfbml: true});
<?php /*
FB_RequireFeatures(["XFBML"], function() {
  	FB.init("<?php echo $key; ?>", "<?php echo plugins_url('/fp/xd_receiver.php'); ?>", <?php echo json_encode($sets); ?>);
});*/ ?>
</script>
<?php
}

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'fp_settings_link', 10, 1);
function fp_settings_link($links) {
	$links[] = '<a href="'.admin_url('options-general.php?page=fp').'">'.__('Settings').'</a>';
	return $links;
}

// add the admin settings and such
add_action('admin_init', 'fp_admin_init',9);
function fp_admin_init(){
	$options = fp_options();
	if (empty($options['api_key']) || empty($options['app_secret']) || empty($options['appid'])) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".sprintf(__('FacePress needs configuration information on its <a href="%s">settings</a> page.'), admin_url('options-general.php?page=fp'))."</p></div>';" ) );
	}
	//add_action('admin_head','fp_featureloader',20);
	add_action('admin_footer','fp_add_base_js',20);
	wp_enqueue_script('jquery');
	register_setting( 'fp_options', 'fp_options', 'fp_options_validate' );
	add_settings_section('fp_main', __('FacePress Main Settings'), 'fp_section_text', 'fp');
	if (!defined('FACEBOOK_API_KEY')) add_settings_field('fp_api_key', __('Facebook API Key'), 'fp_setting_api_key', 'fp', 'fp_main');
	if (!defined('FACEBOOK_APP_SECRET')) add_settings_field('fp_app_secret', __('Facebook Application Secret'), 'fp_setting_app_secret', 'fp', 'fp_main');
	if (!defined('FACEBOOK_APP_ID')) add_settings_field('fp_appid', __('Facebook Application ID'), 'fp_setting_appid', 'fp', 'fp_main');
	if (!defined('FACEBOOK_FANPAGE')) add_settings_field('fp_fanpage', __('Facebook Fan Page'), 'fp_setting_fanpage', 'fp', 'fp_main');
}

// add the admin options page
add_action('admin_menu', 'fp_admin_add_page');
function fp_admin_add_page() {
	$mypage = add_options_page(__('FacePress'), __('FacePress'), 'manage_options', 'fp', 'fp_options_page');
}

// display the admin options page
function fp_options_page() {
?>
	<div class="wrap">
	<h2><?php _e('FacePress'); ?></h2>
	<p><?php _e('Options related to the FacePress plugin.'); ?></p>
	<form method="post" action="options.php">
	<?php settings_fields('fp_options'); ?>
	<table><tr><td>
	<?php do_settings_sections('fp'); ?>	
	</td></tr></table>
	<p class="submit">
	<input type="submit" name="Submit" class="button-primary" value="<?php esc_attr_e('Save Changes') ?>" />
	</p>
	</form>
	</div>
	
<?php
}

function fp_section_text() {
	$options = fp_options();
	if (empty($options['api_key']) || empty($options['app_secret']) || empty($options['appid'])) {
?>
<p><?php _e('To connect your site to Facebook, you will need a Facebook Application. 
If you have already created one, please insert your API key, Application Secret, and Application ID below.'); ?></p>
<p><strong><?php _e('Can&#39;t find your key?'); ?></strong></p>
<ol>
<li><?php _e('Get a list of your applications from here: <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a>'); ?></li>
<li><?php _e('Select the application you want, then copy and paste the API key, Application Secret, and Application ID from there.'); ?></li>
</ol>

<p><strong><?php _e('Haven&#39;t created an application yet?</strong> Don&#39;t worry, it&#39;s easy!'); ?></p>
<ol>
<li><?php _e('Go to this link to create your application: <a target="_blank" href="http://developers.facebook.com/setup.php">Facebook Connect Setup</a>'); ?></li>
<li><?php _e('When it tells you to "Upload a file" on step 2, just hit the "Upload Later" button. This plugin takes care of that part for you!'); ?></li>
<li><?php _e('On the final screen, there will be an API Key field, in the yellow box. Copy and paste that information into here.'); ?></li>
<li><?php _e('You can get the rest of the information from the application on the 
<a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a> page.'); ?></li>
<li><?php _e('Select the application you want, then copy and paste the API key, Application Secret, and Application ID from there.'); ?></li>
</ol>
<?php
		// look for an FBFoundations key if we dont have one of our own, 
		// to better facilitate switching from that plugin to this one.
		$fbfoundations_settings = get_option('fbfoundations_settings');
		if (isset($fbfoundations_settings['api_key']) && !empty($fbfoundations_settings['api_key'])) {
			$options['api_key'] = $fbfoundations_settings['api_key'];
		}
	} else {

		// load facebook platform
		include_once 'facebook.php';
		$fb=new Facebook($options['api_key'], $options['app_secret']);

		$error = false;
		
		try {
    		$a = $fb->api_client->admin_getAppProperties(array('connect_url'));
		} catch (Exception $e) {
		    // bad API key or secret or something
		    $error=true;
		    echo '<p class="error">'.__('Facebook doesn&#39;t like your settings, it says: ');
		    echo $e->getMessage();
		    echo '.</p>';
		}
		
		if (is_array($a)) {
			$connecturl = $a['connect_url'];
		} else if (is_object($a)) { // seems to happen on some setups.. dunno why.
			$connecturl = $a->connect_url;
		}
		
		if (WP_DEBUG && !empty($connecturl)) {
			$siteurl = trailingslashit(get_option('siteurl'));
			if (@strpos($siteurl, $connecturl) === false) {
				$error = true;
				echo '<p class="error">'.sprintf(__('Your Facebook Application\'s "Connect URL" is configured incorrectly. It is currently set to "%s" when it should be set to "%s" .'), $connecturl, $siteurl) . '</p>';
			}

			if ($error) {
?>
<p class="error"><?php sprintf(_e('To correct these errors, you may need to <a href="http://www.facebook.com/developers/editapp.php?app_id=%s">edit your applications settings</a> and correct the values therein. The site will not work properly until the errors are corrected.'), $options['appid']); ?></p>
<?php
			}
		}
	}
}

function fp_get_login_button($text = '', $perms = '', $onlogin = '', $data = array() ) {
	$return = '<fb:login-button v="2" perms="'.$perms.'" onlogin="'.$onlogin.'"';
	foreach( $data as $k => $v ) {
		$return .= " $k=\"$v\"";
	}
	$return .= '>' . $text . '</fb:login-button>';
	return apply_filters('fp_get_login_button', $return, $text, $perms, $onlogin, $data );
}

// this will override all the main options if they are pre-defined
function fp_override_options($options) {
	if (defined('FACEBOOK_APP_ID')) $options['appid'] = FACEBOOK_APP_ID;
	if (defined('FACEBOOK_APP_KEY')) $options['api_key'] = FACEBOOK_APP_KEY;
	if (defined('FACEBOOK_APP_SECRET')) $options['app_secret'] = FACEBOOK_APP_SECRET;
	if (defined('FACEBOOK_FANPAGE')) $options['fanpage'] = FACEBOOK_FANPAGE;
	return $options;
}
add_filter('option_fp_options', 'fp_override_options');

function fp_setting_appid() {
	if (defined('FACEBOOK_APP_ID')) return;
	$options = fp_options();
	echo "<input type='text' id='fpappid' name='fp_options[appid]' value='{$options['appid']}' size='40' /> ".__('(required)');
	if (!empty($options['appid'])) echo '<p>'.sprintf(__('Here is a <a href="http://www.facebook.com/apps/application.php?id=%s&amp;v=wall">link to your applications wall</a>. There you can give it a name, upload a profile picture, things like that. Look for the &quot;Edit Application&quot; link to modify the application.'), $options['appid']).'</p>';
}
function fp_setting_api_key() {
	if (defined('FACEBOOK_APP_KEY')) return;
	$options = fp_options();
	echo "<input type='text' id='fpapikey' name='fp_options[api_key]' value='{$options['api_key']}' size='40' /> ".__('(required)');
}
function fp_setting_app_secret() {
	if (defined('FACEBOOK_APP_SECRET')) return;
	$options = fp_options();
	echo "<input type='text' id='fpappsecret' name='fp_options[app_secret]' value='{$options['app_secret']}' size='40' /> ".__('(required)');
}
function fp_setting_fanpage() {
	if (defined('FACEBOOK_FANPAGE')) return;
	$options = get_option('fp_options'); ?>

<p><?php _e('Some sites use Fan Pages on Facebook to connect with their users. The Application wall acts as a  Fan Page in all respects, however some sites have been using Fan Pages previously, and already have communities and content built around them. Facebook offers no way to migrate these, so the option to use an existing Fan Page is offered for people with this situation. Note that this doesn&#39;t <em>replace</em> the application, as that is not optional. However, you can use a Fan Page for specific parts of the FacePress plugin, such as the Fan Box, the Publisher, and the Chicklet.'); ?></p>

<p><?php _e('If you have a <a href="http://www.facebook.com/pages/manage/">Fan Page</a> that you want to use for your site, enter the ID of the page here. Most users should leave this blank.'); ?></p>

<?php
	echo "<input type='text' id='fpfanpage' name='fp_options[fanpage]' value='{$options['fanpage']}' size='40' />";
}

// validate our options
function fp_options_validate($input) {
	if (!defined('FACEBOOK_APP_KEY')) {
		// api keys are 32 bytes long and made of hex values
		$input['api_key'] = trim($input['api_key']);
		if(! preg_match('/^[a-f0-9]{32}$/i', $input['api_key'])) {
		  $input['api_key'] = '';
		}
	}

	if (!defined('FACEBOOK_APP_SECRET')) {
		// api keys are 32 bytes long and made of hex values
		$input['app_secret'] = trim($input['app_secret']);
		if(! preg_match('/^[a-f0-9]{32}$/i', $input['app_secret'])) {
		  $input['app_secret'] = '';
		}
	}

	if (!defined('FACEBOOK_APP_ID')) {
		// app ids are big integers
		$input['appid'] = trim($input['appid']);
		if(! preg_match('/^[0-9]+$/i', $input['appid'])) {
		  $input['appid'] = '';
		}
	}
	
	if (!defined('FACEBOOK_FANPAGE')) {
		// fanpage ids are big integers
		$input['fanpage'] = trim($input['fanpage']);
		if(! preg_match('/^[0-9]+$/i', $input['fanpage'])) {
		  $input['fanpage'] = '';
		}
	}
	
	$input = apply_filters('fp_validate_options',$input);
	return $input;
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


// this function checks if the current FB user is a fan of your page. 
// Returns true if they are, false otherwise.
function fp_is_fan($pageid='0') {
	$options = fp_options();

	if ($pageid == '0') {
		if ($options['fanpage']) $pageid = $options['fanpage'];
		else $pageid = $options['appid'];
	}

	include_once 'facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	
	$fbuid=$fb->get_loggedin_user();
	
	if (!$fbuid) return false;

	if ($fb->api_client->pages_isFan($pageid) ) {
		return true;
	} else {
		return false;
	}
}

// get the current facebook user number (0 if the user is not connected to this site)
function fp_get_user() {
	$options = fp_options();
	include_once 'facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	$fbuid=$fb->get_loggedin_user();
	return $fbuid;
}

function get_facebook_cookie() {
  $args = array();
  @parse_str(trim($_COOKIE['fbs_' . fp_options('api_key')], '\\"'), $args);
  ksort($args);
  $payload = '';
  foreach ($args as $key => $value) {
    if ($key != 'sig') {
      $payload .= $key . '=' . $value;
    }
  }
  if (md5($payload . fp_options('app_secret')) != @$args['sig']) {
    return null;
  }
  return $args;
}


function fp_get_current_url() {
	// build the URL in the address bar
	$requested_url  = ( !empty($_SERVER['HTTPS'] ) && strtolower($_SERVER['HTTPS']) == 'on' ) ? 'https://' : 'http://';
	$requested_url .= $_SERVER['HTTP_HOST'];
	$requested_url .= $_SERVER['REQUEST_URI'];
	return $requested_url;
}

function fp_check_connection() {
	
	$options = get_option('fp_options');	
	
	// load facebook platform
	include_once 'facebook.php';
	$fb = new Facebook($options['api_key'], $options['app_secret']);
	
	if( $_SESSION['fb-connected'] ) {
		
		$cookie = get_facebook_cookie();
		
		$fbuid = 0;
		
		if( $cookie ) {
			$fbuid = $cookie['uid'];
		}
		
		if(!preg_match('/^[0-9]+$/i', $fbuid)) {
			  $fbuid = 0;
		}
		
		if( $fbuid == 0 ) {
			$_SESSION['fbuid'] = $fbuid;
			$_SESSION['fb-connected'] = false;
		}
	
	}
	
	return $_SESSION['fb-connected'];
	
}

/*
add_action('init', create_function('','if(isset($_GET["session"] ) && isset( $_GET["next"] ) ) {	wp_redirect(add_query_arg("session", $_GET["session"], $_GET["next"]));exit;}'));
*/
/**
 * FacePress Comments
 */
add_action('admin_init','fp_comm_error_check');
function fp_comm_error_check() {
	if ( get_option( 'comment_registration' ) && fp_options('allow_comments') ) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".__('FacePress Comment function doesn\'t work with sites that require registration to comment.')."</p></div>';" ) );
	}
}

add_action('admin_init', 'fp_comm_admin_init');
function fp_comm_admin_init() {
	add_settings_section('fp_comm', __('Comment Settings', 'fp'), 'fp_comm_section_callback', 'fp');
	add_settings_field('fp_allow_comments', __('Allow Facebook users to comment?', 'fp'), 'fp_setting_allow_comments', 'fp', 'fp_comm');
}

function fp_comm_section_callback() {
	echo '<p>'.__('Allow facebook users to comment with their FB accounts.', 'fp').'</p>';
}

function fp_setting_allow_comments() {
	$options = fp_options();
	echo "<input type='checkbox' id='fpallowcomment' name='fp_options[allow_comment]' value='yes' ".checked($options['allow_comment'],true,false)." />";	
}

add_action('fp_validate_options', 'fp_comm_validate_options');
function fp_comm_validate_options($input) {
	if( isset($input['allow_comment']) && $input['allow_comment'] == 'yes' ) {
		$input['allow_comment'] = true;
	} else {
		$input['allow_comment'] = false;
	}
	return $input;
}

// force load jQuery (we need it later anyway)
function fp_comm_jquery() {
	wp_enqueue_script('jquery');
}

// set a variable to know when we are showing comments (no point in adding js to other pages)
function fp_comm_comments_enable() {
	global $fp_comm_comments_form;
	$fp_comm_comments_form = true;
}

// add placeholder for sending comment to Facebook checkbox
function fp_comm_send_place() {
?><p id="fp_comm_send"></p><?php
}

// hook to the footer to add our scripting
function fp_comm_footer_script() {
	global $fp_comm_comments_form;
	if ($fp_comm_comments_form != true) return; // nothing to do, not showing comments

	if ( is_user_logged_in() ) return; // don't bother with this stuff for logged in users
	
	$options = get_option('fp_options');
?>
<script type="text/javascript">
var fb_connect_user = false;

function fp_update_user_details() {
	fb_connect_user = true;

	// Show their FB details TODO this should be configurable, or at least prettier...
	if (!jQuery('#fb-user').length) {
		jQuery('#alt-comment-login').hide();
		jQuery('#comment-user-details').hide().after("<span id='fb-user'>" +
		"<fb:profile-pic uid='loggedinuser' facebook-logo='true' size='normal' height='50' width='50' class='avatar' id='fb-avatar'></fb:profile-pic>" +
		"<span id='fb-msg'><strong><?php printf(__('Hi %s!'), "<fb:name uid='loggedinuser' useyou='false'></fb:name>"); ?></strong><br /><?php _e('You are connected with your Facebook account.'); ?>" +
		"<a href='#' onclick='FB.Connect.logoutAndRedirect(\"<?php the_permalink() ?>\"); return false;'><?php _e('Logout'); ?></a>" +
		"</span></span>");
		jQuery('#fp_comm_send').html('<input style="width: auto;" type="checkbox" id="fp_comm_share" /><label for="fp_comm_send"><?php _e('Share Comment on Facebook'); ?></label>');
	}

	// Refresh the DOM
	FB.XFBML.Host.parseDomTree();
}

jQuery("#commentform").bind('submit',fp_handle_submit_share);
function fp_handle_submit_share() {
	if (jQuery('#fp_comm_share:checked').val() == 'on') {
		fp_setCookie('fp_share', 'yes');
	}
	return true;
}

<?php if (get_option('require_name_email')) { ?>
// first, check if we already have email permission
var fp_comm_email_perm = false;

FB.Facebook.apiClient.users_hasAppPermission('email',function(res,ex){
	if (res == 0) {
		// no permission, ask for it on submit
		jQuery("#commentform").bind('submit',fp_get_email_perms);
	} else {
		// we have permission, no special handler needed
		fp_comm_email_perm = true;
	}
});

// ask for email permission
function fp_get_email_perms() {
	if (fp_comm_email_perm) return true;
	if (fb_connect_user) {
		FB.Facebook.apiClient.users_hasAppPermission('email',function(res,ex){
			if (res == 0) {
				FB.Connect.showPermissionDialog("email", function(perms) {
					if (perms.match("email")) {
						fp_commentform_submit();
					} else {
						var dialog = FB.UI.FBMLPopupDialog('Email required', '');
						var fbml='\
<div id="fb_dialog_content" class="fb_dialog_content">\
	<div class="fb_confirmation_stripes"></div>\
	<div class="fb_confirmation_content"><p>This site requires permission to get your email address for you to leave a comment. You can not leave a comment without granting that permission.</p></div>\
</div>';
						dialog.setFBMLContent(fbml);
						dialog.setContentWidth(540); 
						dialog.setContentHeight(65);
						dialog.set_placement(FB.UI.PopupPlacement.topCenter);
						dialog.show();
						setTimeout ( function() { dialog.close(); }, 5000 );					
					}
				});
			} else {
				fp_commentform_submit();
			}
		});
		return false;
	} else {
		return true;
	}	
}

// submit the form
function fp_commentform_submit() {
	jQuery("#commentform").unbind('submit',fp_get_email_perms);
	jQuery("#commentform :submit").click();
}
<?php } ?>

function fp_setCookie(c_name,value,expiredays) {
	var exdate=new Date();
	exdate.setDate(exdate.getDate()+expiredays);
	document.cookie=c_name+ "=" +escape(value)+((expiredays==null) ? "" : ";expires="+exdate.toGMTString());
}

function fp_getCookie(c_name) {
	if (document.cookie.length>0) {
		c_start=document.cookie.indexOf(c_name + "=");
		if (c_start!=-1) {
			c_start=c_start + c_name.length+1;
			c_end=document.cookie.indexOf(";",c_start);
			if (c_end==-1) c_end=document.cookie.length;
			return unescape(document.cookie.substring(c_start,c_end));
		}
	}
	return "";
}

FB.Connect.ifUserConnected(fp_update_user_details);
if (fp_getCookie('fp_share') == 'yes') {
	fp_setCookie('fp_share', null);
	<?php
		global $post;
		// build the attachment
		$permalink = get_permalink($post->ID);
		$attachment['name'] = get_the_title();
		$attachment['href'] = get_permalink();
		$attachment['description'] = fp_comm_make_excerpt($post->post_content);
		$attachment['caption'] = '{*actor*} left a comment on '.get_the_title();
		$attachment['comments_xid'] = urlencode(get_permalink());
					
		$action_links[0]['text'] = 'Read Post';
		$action_links[0]['href'] = get_permalink();
	?>
	
	FB.Connect.streamPublish(null, 
		<?php echo json_encode($attachment); ?>,
		<?php echo json_encode($action_links); ?>
		);
}
</script>
<?php
}

// I wish wp_trim_excerpt was easier to use separately...
function fp_comm_make_excerpt($text) {
	$text = strip_shortcodes( $text );
	remove_filter( 'the_content', 'wptexturize' );
	$text = apply_filters('the_content', $text);
	add_filter( 'the_content', 'wptexturize' );
	$text = str_replace(']]>', ']]&gt;', $text);
	$text = wp_strip_all_tags($text);
	$text = str_replace(array("\r\n","\r","\n"),' ',$text);
	$excerpt_length = apply_filters('excerpt_length', 55);
	$excerpt_more = apply_filters('excerpt_more', '[...]');
	$words = explode(' ', $text, $excerpt_length + 1);
	if (count($words) > $excerpt_length) {
		array_pop($words);
		array_push($words, $excerpt_more);
		$text = implode(' ', $words);
	}
	return $text;
}

function fp_comm_login_button() {
	echo '<p id="fb-connect">'.fp_get_login_button(__('Connect with Facebook'), 'email', 'fp_update_user_details();') . '</p>';
}

if( !function_exists('alt_comment_login') ) {
	
	function alt_comment_login() {
		echo '<div id="alt-comment-login">';
		do_action('alt_comment_login');
		echo '</div>';
	}
	
	function comment_user_details_begin() { echo '<div id="comment-user-details">'; }
	
	function comment_user_details_end() { echo '</div>'; }
	
}

// generate facebook avatar code for FB user comments
add_filter('get_avatar','fp_comm_avatar', 10, 5);
function fp_comm_avatar($avatar, $id_or_email, $size = '96', $default = '', $alt = false) {
	// check to be sure this is for a comment
	if ( !is_object($id_or_email) || !isset($id_or_email->comment_ID) || $id_or_email->user_id) 
		 return $avatar;
		 
	// check for fbuid comment meta
	$fbuid = get_comment_meta($id_or_email->comment_ID, 'fbuid', true);
	if ($fbuid) {
		// return the avatar code
		return "<img width='{$size}' height='{$size}' class='avatar avatar-{$size} fbavatar' src='http://graph.facebook.com/{$fbuid}/picture?type=square' />";
	}
	
	// check for number@facebook.com email address (deprecated, auto-converts to new meta data)
	if (preg_match('|(\d+)\@facebook\.com|', $id_or_email->comment_author_email, $m)) {
		// save the fbuid as meta data
		update_comment_meta($id_or_email->comment_ID, 'fbuid', $m[1]);
		
		// return the avatar code
		return "<img width='{$size}' height='{$size}' class='avatar avatar-{$size} fbavatar' src='http://graph.facebook.com/{$m[1]}/picture?type=square' />";
	}
	
	return $avatar;
}

// store the FB user ID as comment meta data ('fbuid')
function fp_comm_add_meta($comment_id) {
	$options = get_option('fp_options');
	include_once 'facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	$fbuid=$fb->get_loggedin_user();
	if ($fbuid) {
		update_comment_meta($comment_id, 'fbuid', $fbuid);
	}
}

// Add user fields for FB commenters
function fp_comm_fill_in_fields($comment_post_ID) {
	if (is_user_logged_in()) return; // do nothing to WP users
	
	$options = get_option('fp_options');
	include_once 'facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	$fbuid=$fb->get_loggedin_user();
	
	// this is a facebook user, override the sent values with FB info
	if ($fbuid) {
		$user_details = $fb->api_client->users_getInfo($fbuid, 'name, profile_url');
		if (is_array($user_details)) {
			$_POST['author'] = $user_details[0]['name']; 
			$_POST['url'] = $user_details[0]['profile_url'];
		}
		
		$query = "SELECT email FROM user WHERE uid=\"{$fbuid}\""; 
		$email = $fb->api_client->fql_query($query);
		if (is_array($email)) {
			$email = $email[0]['email'];
			$_POST['email'] = $email; 
		}
	}
}

if( fp_options('allow_comment') ) {
	add_action('wp_enqueue_scripts','fp_comm_jquery');
	add_action('comment_form','fp_comm_comments_enable');
	add_action('comment_form','fp_comm_send_place');
	add_action('wp_footer','fp_comm_footer_script',30);
	add_action('alt_comment_login', 'fp_comm_login_button');
	add_action('comment_post','fp_comm_add_meta', 10, 1);
	add_filter('pre_comment_on_post','fp_comm_fill_in_fields');
}

/**
 * FacePress Login
 */
// add the section on the user profile page
add_action('profile_personal_options','fp_login_profile_page');

function fp_login_profile_page($profile) {
	$options = fp_options();
?>
	<table class="form-table">
		<tr>
			<th><label><?php _e('Facebook Connect'); ?></label></th>
<?php
	$fbuid = get_user_meta($profile->ID, 'fbuid', true);	
	if (empty($fbuid)) { 
		?>
			<td><p><?php echo fp_get_login_button(__('Connect to Facebook'), 'email', 'fp_login_update_fbuid(0);', array('size' => 'large') ); ?></p></td>
		</tr>
	</table>
	<?php	
	} else { ?>
		<td><p><?php _e('Connected as ', 'fp'); ?>

		<fb:profile-pic size="square" width="32" height="32" uid="<?php echo $fbuid; ?>" linked="true"></fb:profile-pic>
		<fb:name useyou="false" uid="<?php echo $fbuid; ?>"></fb:name>.
		<input type="button" class="button-primary" value="<?php _e('Disconnect', 'fp'); ?>" onclick="fp_login_update_fbuid(1); return false;" />
		</p></td>
	<?php } ?>
	</tr>
	</table>
	<?php
}

add_action('admin_footer','fp_login_update_js',30); 
function fp_login_update_js() {
	if (defined( 'IS_PROFILE_PAGE' )) {
		?>
		<script type="text/javascript">
		function fp_login_update_fbuid(disconnect) {
			var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
			if (disconnect == 1) {
				FB.logout();
			//	var fbuid = 0;
			} else {
			//	var fbuid = FB.Connect.get_loggedInUser();
			}
			var data = {
				'action': 'update_fbuid',
				'disconnect': disconnect
			//	fbuid: fbuid
			}
			jQuery.post(ajax_url, data, function(response) {
				if (response == '1') {
					location.reload(true);
				}
			});
		}
		</script>
		<?php
	}
}

add_action('wp_ajax_update_fbuid', 'fp_login_ajax_update_fbuid');
function fp_login_ajax_update_fbuid() {
	$options = get_option('fp_options');
	$user = wp_get_current_user();
	$hash = fp_login_fb_hash_email($user->user_email);

	// load facebook platform
	include_once 'facebook.php';
	$fb=new Facebook($options['api_key'], $options['app_secret']);
	
	$cookie = get_facebook_cookie();
	
	@$disconnect = (bool) $_POST['disconnect'];
	
	$fbuid = 0;
	
	if( $cookie ) {
		$fbuid = $cookie['uid'];
	}
	
	if(!preg_match('/^[0-9]+$/i', $fbuid)) {
		  $fbuid = 0;
	}
	if (!$disconnect) {
		// verify that users WP email address is a match to the FB email address (for security reasons)
		$aa[0]['email_hash'] = $hash;
		$aa[0]['account_id'] = $user->ID;

		$ret = $fb->api_client->connect_registerUsers(json_encode($aa));
		if (empty($ret)) { 
			// return value is empty, not good
			echo 'Facebook did not know your email address.';
			exit();
		} else {
			// now we check to see if that user gives the email_hash back to us
			$user_details = $fb->api_client->users_getInfo($fbuid, array('email_hashes'));
			if (!empty($user_details[0]['email_hashes'])) {
				
				// go through the hashes returned by getInfo, make sure the one we want is in them
				$valid = false;
				foreach($user_details[0]['email_hashes'] as $check) {
					if ($check == $hash) $valid = true;
				}
			
				if (!$valid) {
					// no good
					echo 'Facebook could not confirm your email address.';
					exit();
				}
			}
		}
	} else {
		// user disconnecting, so disconnect them in FB too
		$aa[0] = $hash;
		$ret = $fb->api_client->connect_unregisterUsers(json_encode($aa));
		
		// we could check here, but why bother? just assume it worked.
	}
	
	update_usermeta($user->ID, 'fbuid', $fbuid);
	echo 1;
	exit();
}

// computes facebook's email hash thingy. See http://wiki.developers.facebook.com/index.php/Connect.registerUsers
function fp_login_fb_hash_email($email) {
	$email = strtolower(trim($email));
	$c = crc32($email);
	$m = md5($email);
	$fbhash = sprintf('%u_%s',$c,$m);
	return $fbhash;
}
	
add_action('login_form','fp_login_add_login_button');
function fp_login_add_login_button() {
	global $action;
	?>
	<script type="text/javascript">
	function do_fp_login() {
		FB.login(function(response){
			if (response.session){fp_login();}
		},{perms:'email'});
	}
	function fp_login() {
		FB.getLoginStatus(function(response) {
		  if (response.session) {
			// logged in and connected user, someone you know
			window.location='<?php echo esc_js(remove_query_arg('reauth', fp_get_current_url())); ?>';
		  }
		});
	}
	</script>
    <input id="fb-login" type="hidden" name="fb-ogin" value="0" />
	<?php
	$style = apply_filters('fp_login_button_style', ' style="text-align: center; margin: 5px 0;"');
	if ($action == 'login') echo '<p id="fb-login"'.$style.'>' . fp_get_login_button(__('Connect with Facebook'), 'email', 'fp_login();') . '</p>';
}

add_filter('authenticate','fp_login_check',90);
function fp_login_check($user) {
	if ( is_a($user, 'WP_User') ) { return $user; } // check if user is already logged in, skip FB stuff

	$options = get_option('fp_options');	
	
	// load facebook platform
	include_once 'facebook.php';
	$fb = new Facebook($options['api_key'], $options['app_secret']);
	
	if( !$_SESSION['fb-connected'] ) {
		
		$cookie = get_facebook_cookie();
		
		$fbuid = 0;
		
		if( $cookie ) {
			$fbuid = $cookie['uid'];
		}
		
		if(!preg_match('/^[0-9]+$/i', $fbuid)) {
			  $fbuid = 0;
		}
		
		$_SESSION['fbuid'] = $fbuid;
	
	}
	
	if($_SESSION['fb-connected'] || $fbuid):
	    try {
	        $test = $fb->api( array( 'method'=>'fql.query',
			  'query'=>'SELECT uid, pic_square, first_name FROM user WHERE uid = '.$_SESSION['fbuid']
			));
	        if (count($test) && $test[0]['uid'] == $_SESSION['fbuid']) {
				global $wpdb;
				$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuid' AND meta_value = %s", $_SESSION['fbuid']) );
				
				if ($user_id) {
					$user = new WP_User($user_id);
					$_SESSION['fb-connected'] = true;
				} else {
					do_action('fp_login_new_fb_user',$fb); // hook for creating new users if desired
					global $error;
					$error = __('<strong>ERROR</strong>: Facebook user not recognized.');
				}
			}

	    } catch (Exception $ex) {
	        $fb->clear_cookie_state();
	    }
	    
	endif;
		
	return $user;	
}

add_action('wp_logout','fp_logout');
function fp_logout() {
	$options = get_option('fp_options');	
	
	if (fp_check_connection()) {
		add_action('login_form','fp_js_logout', 30);
		$_SESSION['fb-connected'] = false;
		$_SESSION['fbuid'] = 0;
		setcookie('fbs_' . fp_options('api_key'), "", time() - 3600);
	}
}

function fp_js_logout() {
	?><script type="text/javascript">
FB.logout();
</script><?php
}

add_action('login_form','fp_add_base_js');

/**
 * FacePress like button.
 */
global $fblike_defaults;
$fblike_defaults = array(
	'id'=>0,
	'showfaces'=>'true',
	'width'=>'260',
	'colorscheme'=>'light',
);

function get_fblike($args='') {
	global $fblike_defaults;
	$args['css'] = esc_attr(fp_options('like_css'));
	$args = apply_filters('fblike_args', wp_parse_args($fblike_defaults, $args));
	extract($args);
	
	$url = get_permalink($id);
	
	return "<div class=\"fblike\" style=\"$css\"><fb:like href='{$url}' layout='{$layout}' show_faces='{$showfaces}' width='{$width}' action='{$action}' colorscheme='{$colorscheme}' /></div>";
}

function fblike($args) {
	echo get_fblike($args);
}

function fblike_shortcode($atts) {
	global $fblike_defaults;
	$args = shortcode_atts($fblike_defaults, $atts);

	return get_fp_like_button($args);
}
add_shortcode('fb-like', 'fblike_shortcode');

function fblike_automatic($content) {
	$options = fp_options();
	
	$args = array(
		'layout'=>$options['like_layout'],
		'action'=>$options['like_action'],
	);
	
	$button = get_fblike($args);
	switch ($options['like_position']) {
		case "before":
			$content = $button . $content;
			break;
		case "after":
			$content = $content . $button;
			break;
		case "both":
			$content = $button . $content . $button;
			break;
		case "manual":
		default:
			break;
	}
	return $content;
}
add_filter('the_content', 'fblike_automatic', 30);

// add the admin sections to the fp page
add_action('admin_init', 'fp_like_admin_init');
function fp_like_admin_init() {
	add_settings_section('fp_like', __('Like Button Settings'), 'fp_like_section_callback', 'fp');
	add_settings_field('fp_like_position', __('Like Button Position'), 'fp_like_position', 'fp', 'fp_like');
	add_settings_field('fp_like_layout', __('Like Button Layout'), 'fp_like_layout', 'fp', 'fp_like');
	add_settings_field('fp_like_action', __('Like Button Action'), 'fp_like_action', 'fp', 'fp_like');
	add_settings_field('fp_like_css', __('Like Button CSS'), 'fp_like_css', 'fp', 'fp_like');
}

function fp_like_section_callback() {
	echo '<p>'.__('Choose where you want the like button added to your content.').'</p>';
}

function fp_like_position() {
	$options = fp_options();
	if (!$options['like_position']) $options['like_position'] = 'manual';
	?>
	<ul>
	<li><label><input type="radio" name="fp_options[like_position]" value="before" <?php checked('before', $options['like_position']); ?> /> <?php _e('Before the content of your post'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_position]" value="after" <?php checked('after', $options['like_position']); ?> /> <?php _e('After the content of your post'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_position]" value="both" <?php checked('both', $options['like_position']); ?> /> <?php _e('Before AND After the content of your post'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_position]" value="manual" <?php checked('manual', $options['like_position']); ?> /> <?php _e('Manually add the button to your theme or posts (use the get_fblike function in your theme)'); ?></label></li>
	</ul>
<?php 
}

function fp_like_layout() {
	$options = fp_options();
	if (!$options['like_layout']) $options['like_layout'] = 'standard';
	?>
	<ul>
	<li><label><input type="radio" name="fp_options[like_layout]" value="standard" <?php checked('standard', $options['like_layout']); ?> /> <?php _e('Standard'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_layout]" value="button_count" <?php checked('button_count', $options['like_layout']); ?> /> <?php _e('Button with counter'); ?></label></li>
	</ul>
<?php 
}

function fp_like_action() {
	$options = fp_options();
	if (!$options['like_action']) $options['like_action'] = 'like';
	?>
	<ul>
	<li><label><input type="radio" name="fp_options[like_action]" value="like" <?php checked('like', $options['like_action']); ?> /> <?php _e('Like'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_action]" value="recommend" <?php checked('recommend', $options['like_action']); ?> /> <?php _e('Recommend'); ?></label></li>
	</ul>
<?php 
}
function fp_like_css() {
	$options = fp_options();
	if (!$options['like_css']) $options['like_css'] = '';
	echo "<input type='text' id='fp-like-style' name='fp_options[like_css]' value='{$options['like_css']}' size='40' /> " . __('the css style of the like button.', 'fp');
}

add_filter('fp_validate_options','fp_like_validate_options');
function fp_like_validate_options($input) {
	if (!in_array($input['like_position'], array('before', 'after', 'both', 'manual'))) {
			$input['like_position'] = 'manual';
	}
	return $input;
}

add_action('og_meta','fp_like_meta');
function fp_like_meta() {
	$excerpt = '';
	if (is_singular()) {
		the_post();
		rewind_posts(); 
		$excerpt = strip_tags(get_the_excerpt());
		$content = get_the_content();
		$content = apply_filters('the_content', $content);
?>
<meta property="og:type" content="article" />
<meta property="og:title" content="<?php echo esc_attr(get_the_title()); ?>" />
<?php	
	} else if (is_home()) {
	?>
<meta property="og:type" content="blog" />
<meta property="og:title" content="<?php bloginfo('name'); ?>" />
<?php
	}
}

