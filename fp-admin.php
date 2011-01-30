<?php
/**
 * FacePress Admin
 */

// action links
add_filter('plugin_action_links_'.plugin_basename(__FILE__), 'fp_settings_link', 10, 1);
function fp_settings_link($links) {
	$links[] = '<a href="'.admin_url('options-general.php?page=fp').'">'.__('Settings', 'fp').'</a>';
	return $links;
}
require_once 'fp-admin.php';

function user_can_edit_fp_app_options() {
    return !fp_app_options_defined() &&
            ( is_multisite() ? is_super_admin() : current_user_can('manage_options') );
}

// add the admin options page
add_action('admin_menu', 'fp_admin_add_page');
function fp_admin_add_page() {
	global $wp_version;
	add_options_page(__('FacePress', 'fp'), __('FacePress', 'fp'), 'manage_options', 'fp', 'fp_options_page');
    if( (!is_multisite() || version_compare($wp_version, '3.1-dev', '<')) && user_can_edit_fp_app_options() ) {
        add_submenu_page((is_multisite()?'ms-admin':'options-general').'.php', __('FacePress App', 'fp'), __('FacePress App', 'fp'), 'manage_options', 'fpapp', 'fp_app_options_page');
    }
}
add_action('network_admin_menu', 'fp_network_admin_add_page');
function fp_network_admin_add_page() {
    if( is_multisite() && user_can_edit_fp_app_options() ) {
        add_submenu_page('settings.php', __('FacePress App', 'fp'), __('FacePress App', 'fp'), 'manage_options', 'fpapp', 'fp_app_options_page');
    }
}

// add the admin settings and such
add_action('admin_init', 'fp_admin_init',9);
function fp_admin_init(){

	wp_enqueue_script('jquery');

    $options = fp_options();

    if (!fp_ready() && user_can_edit_fp_app_options()) {
            add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".sprintf(__('FacePress needs to be configured on its <a href="%s">app settings</a> page.', 'fp'), admin_url('options-general.php?page=fpapp'))."</p></div>';" ) );
    }
    register_setting( 'fp_options', 'fp_options', 'fp_options_validate' );

    if ( user_can_edit_fp_app_options() ) {
        register_setting( 'fp_app_options', 'fp_app_options' );
        add_filter('pre_update_option_fp_app_options','fp_update_app_options', 10, 2 );
        
		add_settings_section('fp_app_settings', __('App Settings', 'fp'),
		            'fp_app_settings_callback', 'fpapp');
		add_settings_field('fp-app-id', __('Facebook App ID', 'fp'),
		            'fp_setting_app_id', 'fpapp', 'fp_app_settings' );
		add_settings_field('fp-app-key', __('Facebook App Key', 'fp'),
		            'fp_setting_app_key', 'fpapp', 'fp_app_settings' );
		add_settings_field('fp-app-secret', __('Facebook App Secret', 'fp'),
		            'fp_setting_app_secret', 'fpapp', 'fp_app_settings' );
		add_settings_field('fp-fanpage', __('Facebook Fan Page ID', 'fp'),
		            'fp_setting_fanpage', 'fpapp', 'fp_app_settings' );
    }
}

// display the admin options page
function fp_options_page() {
?>
    <div class="wrap">
        <h2><?php _e('FacePress', 'fp'); ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('fp_options'); ?>
            <table><tr><td>
                <?php do_settings_sections('fp'); ?>
            </td></tr></table>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'fp') ?>" />
            </p>
        </form>
    </div>

<?php
}
function fp_app_options_page() {
    if( isset( $_POST['option_page'] ) && $_POST['option_page'] == 'fp_app_options' 
                    && wp_verify_nonce($_POST['_wpnonce'], 'fp_app_options') ) {
        // Save options...
        $options = $_POST['fp_app_options'];
        update_option('fp_app_options', $options);
        echo '<div id="message" class="updated"><p>'.__('Options saved.').'</p></div>';
    }
?>
    <div class="wrap">
        <h2><?php _e('FacePress App Options', 'fp'); ?></h2>
        <form method="post">
            <input type='hidden' name='option_page' value='fp_app_options' />
            <?php wp_nonce_field('fp_app_options'); ?>
            <table><tr><td>
                <?php do_settings_sections('fpapp'); ?>
            </td></tr></table>
            <p class="submit">
                <input type="submit" class="button-primary" value="<?php esc_attr_e('Save Changes', 'fp') ?>" />
            </p>
        </form>
        <p><?php _e('If you like this plugin, Follow me <a href="http://twitter.com/l0uy">@l0uy</a> for more updates.', 'fp'); ?></p>
    </div>
<?php
}

function fp_app_settings_callback() {
	if (!fp_ready()) {
?>
<p><?php _e('To connect your site to Facebook, you will need a Facebook Application. If you have already created one, please insert your API key, Application Secret, and Application ID below.', 'fp'); ?></p>
<p><strong><?php _e('Can&#39;t find your key?', 'fp'); ?></strong></p>
<ol>
<li><?php _e('Get a list of your applications from here: <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a>', 'fp'); ?></li>
<li><?php _e('Select the application you want, then copy and paste the API key, Application Secret, and Application ID from there.', 'fp'); ?></li>
</ol>

<p><strong><?php _e('Haven&#39;t created an application yet?</strong> Don&#39;t worry, it&#39;s easy!', 'fp'); ?></p>
<ol>
<li><?php _e('Go to this link to create your application: <a target="_blank" href="http://developers.facebook.com/setup.php">Facebook Connect Setup</a>', 'fp'); ?></li>
<li><?php _e('When it tells you to "Upload a file" on step 2, just hit the "Upload Later" button. This plugin takes care of that part for you!', 'fp'); ?></li>
<li><?php _e('On the final screen, there will be an API Key field, in the yellow box. Copy and paste that information into here.', 'fp'); ?></li>
<li><?php _e('You can get the rest of the information from the application on the <a target="_blank" href="http://www.facebook.com/developers/apps.php">Facebook Application List</a> page.', 'fp'); ?></li>
<li><?php _e('Select the application you want, then copy and paste the API key, Application Secret, and Application ID from there.', 'fp'); ?></li>
</ol>
<?php
	}
}

function fp_setting_app_id() {
	if (defined('FACEBOOK_APP_ID')) return;
	echo "<input type='text' name='fp_app_options[appId]' value='".fp_options('appId')."' size='40' /> " . __('(required)', 'tp');
}

function fp_setting_app_key() {
	if (defined('FACEBOOK_APP_KEY')) return;
	echo "<input type='text' name='fp_app_options[key]' value='".fp_options('key')."' size='40' /> " . __('(required)', 'tp');
}

function fp_setting_app_secret() {
	if (defined('FACEBOOK_APP_SECRET')) return;
	echo "<input type='text' name='fp_app_options[secret]' value='".fp_options('secret')."' size='40' /> " . __('(required)', 'tp');
}

function fp_setting_fanpage() {
	if (defined('FACEBOOK_FANPAGE')) return;
	
	if( fp_options('fanpage') ) { ?>

<p><?php _e('Some sites use Fan Pages on Facebook to connect with their users. The Application wall acts as a  Fan Page in all respects, however some sites have been using Fan Pages previously, and already have communities and content built around them. Facebook offers no way to migrate these, so the option to use an existing Fan Page is offered for people with this situation. Note that this doesn&#39;t <em>replace</em> the application, as that is not optional. However, you can use a Fan Page for specific parts of the FacePress plugin, such as the Fan Box, the Publisher, and the Chicklet.', 'fp'); ?></p>

<p><?php _e('If you have a <a href="http://www.facebook.com/pages/manage/">Fan Page</a> that you want to use for your site, enter the ID of the page here. Most users should leave this blank.', 'fp'); ?></p>

<?php }
	
	echo "<input type='text' name='fp_app_options[fanpage]' value='".fp_options('fanpage')."' size='40' />";
}

// validate our options
function fp_options_validate($input) {
	unset($input['appId'], $input['key'], $input['secret'], $input['fanpage']);
	$input = apply_filters('fp_validate_options',$input);
	return $input;
}
function fp_update_app_options($new, $old) {
    $output = array(
        'appId'   => $new['appId'],
        'key'     => $new['key'],
        'secret'  => $new['secret'],
        'fanpage' => $new['fanpage'],
    );
    
	$input = apply_filters('fp_validate_app_options',$input);

    if( is_multisite() ) {
        if( $output != $old )
            update_site_option('fp_app_options', $output);
    }

    return $output;
}

// validate our options
add_filter('fp_validate_app_options', 'fp_validate_app_options');
function fp_validate_app_options($input) {

	// api keys are 32 bytes long and made of hex values
	$input['key'] = trim($input['key']);
	if(! preg_match('/^[a-f0-9]{32}$/i', $input['key'])) {
	  $input['key'] = '';
	}

	// api keys are 32 bytes long and made of hex values
	$input['secret'] = trim($input['secret']);
	if(! preg_match('/^[a-f0-9]{32}$/i', $input['secret'])) {
	  $input['secret'] = '';
	}

	// app ids are big integers
	$input['appId'] = trim($input['appId']);
	if(! preg_match('/^[0-9]+$/i', $input['appId'])) {
	  $input['appId'] = '';
	}

	// fanpage ids are big integers
	$input['fanpage'] = trim($input['fanpage']);
	if(! preg_match('/^[0-9]+$/i', $input['fanpage'])) {
	  $input['fanpage'] = '';
	}
	
	return $input;
}


