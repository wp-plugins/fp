<?php
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
			<th><label><?php _e('Facebook', 'tp'); ?></label></th>
<?php
	$fbuid = get_user_meta($profile->ID, 'fbuid', true);
	if (empty($fbuid)) {
		?>
			<td><?php echo fp_get_connect_button('login_connect'); ?></td>
	<?php
	} else {
		$obj = wp_remote_get('https://graph.facebook.com/'.$fbuid.'?access_token='.$_SESSION['fp_access_token']['access_token']);
		$obj = json_decode($obj['body']);
		?>
		<td><p><?php _e('Connected as ', 'fp'); ?></p>
			<table><tr><td>
				<a href="http://graph.facebook.com/<?php echo $fbuid; ?>"><img src="http://graph.facebook.com/<?php echo $fbuid; ?>/picture?size=square" width="32" height="32" /></a>
			</td><td>
				<strong><?php echo $obj->name; ?></strong>
			</td></tr><tr><td colspan="2">
				<input type="button" class="button-primary" value="<?php _e('Disconnect', 'fb'); ?>" onclick="return fp_login_disconnect()" />
			</td></tr></table>
			
			<script type="text/javascript">
			function fp_login_disconnect() {
				var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
				var data = {
					action: 'disconnect_fbuid',
					fbuid: '<?php echo $fbuid; ?>'
				}
				jQuery.post(ajax_url, data, function(response) {
					if (response == '1') {
						location.reload(true);
					}
				});
				return false;
			}
			</script>
		</td>
	<?php } ?>
	</tr>
	</table>
	<?php
}

add_action('wp_ajax_disconnect_fbuid', 'fp_login_disconnect_fbuid');
function fp_login_disconnect_fbuid() {
	$user = wp_get_current_user();

	$fbuid = get_user_meta($user->ID, 'fbuid', true);
	if ($fbuid == $_POST['fbuid']) {
		delete_usermeta($user->ID, 'fbuid');
	}

	echo 1;
	exit();
}

add_action('fp_login_connect','fp_login_connect');
function fp_login_connect() {
	if (!is_user_logged_in() || !$_SESSION['fb-connected']) return; // this only works for logged in users
	$user = wp_get_current_user();

	$fb = $_SESSION['fb-me'];
	if( $fb->id ) {
		// we have a user, update the user meta
		update_usermeta($user->ID, 'fbuid', $fb->id);
	}
}


function fp_login_add_login_button() {
	global $action;
	$style = apply_filters('fp_login_button_style', ' style="text-align: center;"');
	if ($action == 'login') echo '<p id="fb-login"'.$style.'>'.fp_get_connect_button('login').'</p><br />';
}

function fp_login_check($user) {
	if ( is_a($user, 'WP_User') ) { return $user; } // check if user is already logged in, skip

	if ($fb = fb_me()) {
		global $wpdb;
		$fbuid = $fb->id;
		$user_id = $wpdb->get_var( $wpdb->prepare("SELECT user_id FROM $wpdb->usermeta WHERE meta_key = 'fbuid' AND meta_value = '%s'", $fbuid) );

		if ($user_id) {
			$user = new WP_User($user_id);
		} else {
			do_action('fp_login_new_fb_user',fb_me()); // hook for creating new users if desired
			global $error;
			$error = __('<strong>Error</strong>: Facebook user not recognized.', 'fp');
		}
	}
	return $user;
}

function fp_logout() {
    session_destroy();
}


if( !fp_options('disable_login') ) {
	add_action('login_form','fp_login_add_login_button');
	add_filter('authenticate','fp_login_check');
	add_action('wp_logout','fp_logout');
}