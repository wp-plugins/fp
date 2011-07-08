<?php
/**
 * FacePress Comment
 */

add_action('admin_init','fp_comment_error_check');
function fp_comment_error_check() {
	if ( get_option( 'comment_registration' ) && fp_options('allow_comments') ) {
		add_action('admin_notices', create_function( '', "echo '<div class=\"error\"><p>".__('FacePress Comment function doesn\'t work with sites that require registration to comment.', 'fp')."</p></div>';" ) );
	}
}

add_action('admin_init', 'fp_comment_admin_init');
function fp_comment_admin_init() {
	add_settings_section('fp_comment', __('Comment Settings', 'fp'), 'fp_comment_section_callback', 'fp');
	add_settings_field('fp_allow_comments', __('Allow Facebook users to comment?', 'fp'), 'fp_setting_allow_comments', 'fp', 'fp_comment');
}

function fp_comment_section_callback() {
	echo '<p>'.__('Allow facebook users to comment with their FB accounts.', 'fp').'</p>';
}

function fp_setting_allow_comments() {
	$options = fp_options();
	echo "<input type='checkbox' name='fp_options[allow_comment]' value='yes' ".checked(fp_options('allow_comment'),true,false)." />";	
}

add_action('fp_validate_options', 'fp_comm_validate_options');
function fp_comm_validate_options($input) {
	if( isset($input['allow_comment']) && $input['allow_comment'] == 'yes' ) {
		$input['allow_comment'] = true;
	} else {
		$input['allow_comment'] = false;
	}
	$input['comment_text'] = trim($input['comment_text']);
	return $input;
}

// set a variable to know when we are showing comments (no point in adding js to other pages)
function fp_comm_comments_enable() {
	global $fp_comm_comments_form;
	$fp_comm_comments_form = true;
}

// hook to the footer to add our scripting
function fp_comm_footer_script() {
	global $fp_comm_comments_form;
	if ($fp_comm_comments_form != true) return; // nothing to do, not showing comments

	if ( is_user_logged_in() ) return; // don't bother with this stuff for logged in users
	
	?>
<script type="text/javascript">
	jQuery(function() {
		var ajax_url = '<?php echo admin_url("admin-ajax.php"); ?>';
		var data = { action: 'fp_comm_get_display' }
		jQuery.post(ajax_url, data, function(response) {
			if (response != '0') {
				jQuery('#alt-comment-login').hide();
				jQuery('#comment-user-details').hide().after(response);
				FB.XFBML.parse();
			}
		});
	});
</script>
	<?php
	/*
	$options = get_option('fp_options');
?>
<script type="text/javascript">
var fb_connect_user = false;

function fp_update_user_details() {
	fb_connect_user = true;

	if (!jQuery('#fb-user').length) {
		jQuery('#alt-comment-login').hide();
		jQuery('#comment-user-details').hide().after("<span id='fb-user'>" +
		"<fb:profile-pic uid='loggedinuser' facebook-logo='true' size='square' height='50' width='50' class='avatar' id='fb-avatar'></fb:profile-pic>" +
		"<span id='fb-msg'><strong><?php printf(__('Hi %s!', 'fp'), "<fb:name uid='loggedinuser' useyou='false'></fb:name>"); ?></strong><br /><?php _e('You are connected with your Facebook account.', 'fp'); ?>" +
		"<a href='#' onclick='FB.logout(function(){window.location=\"<?php the_permalink() ?>\"}); return false;'><?php _e('Logout', 'fp'); ?></a>" +
		"</span></span>");
	}

	// Refresh the DOM
	FB.XFBML.parse();
}

<?php if (get_option('require_name_email')) { ?>
// first, check if we already have email permission
var fp_comm_email_perm = false;

FB.api({method:'users.hasAppPermission', ext_perm:'email'},function(res){
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
	if (fb_connect_user) {
		if (fp_comm_email_perm) return true;
			FB.Connect.showPermissionDialog("email", function(perms) {
				if (perms.match("email")) {
					fp_commentform_submit();
					return true;
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
<?php*/
}

function fp_comm_get_display() {
	$fb = fb_me();
	if ($fb) {
		echo '<div id="fb-user">'.
			 '<fb:profile-pic uid="'.$fb->id.'" width="50" height="50" id="fb-avatar" class="avatar" />'.
			 '<h3 id="fb-msg">' . sprintf(__('Hi %s!', 'fp'), $fb->name) . '</h3>'.
			 '<p>'.__('You are connected with your Facebook account.', 'fp').'</p>'.
			 apply_filters('fp_user_logout','<a href="' . esc_attr(add_query_arg('facebook-logout', '1', fp_get_current_url())) . '" id="fb-logout">'.__('Logout', 'fp').'</a>').
			 '</div>';
		exit;
	}

	echo 0;
	exit;
}

// check for logout request
function fp_comm_logout() {
	if ($_GET['facebook-logout']) {
		session_unset();
		$page = fp_get_current_url();
		wp_redirect(remove_query_arg('facebook-logout', $page));
		exit;
	}
}

function fp_comm_login_button() {
	echo '<p id="fb-connect">'.fp_get_connect_button('comment', 'email') . '</p>';
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
	
	return $avatar;
}

// store the FB user ID as comment meta data ('fbuid')
function fp_comm_add_meta($comment_id) {
	$fb=fb_me();
	$fbuid=$fb->id;
	if ($fbuid) {
		update_comment_meta($comment_id, 'fbuid', $fbuid);
	}
}

// Add user fields for FB commenters
function fp_comm_fill_in_fields($comment_post_ID) {
	if (is_user_logged_in()) return; // do nothing to WP users
	
	$fb=fb_me();
	$fbuid=$fb->id;
	
	// this is a facebook user, override the sent values with FB info
	if ($fbuid) {
		$_POST['author'] = $fb->name; 
		$_POST['url'] = $fb->link;
		$_POST['email'] = $fb->email; 
	}
}

if( fp_options('allow_comment') ) {
	add_action('comment_form','fp_comm_comments_enable');
	add_action('wp_footer','fp_comm_footer_script',30);
    add_action('wp_ajax_nopriv_fp_comm_get_display', 'fp_comm_get_display');
    add_action('init','fp_comm_logout');
	add_action('alt_comment_login', 'fp_comm_login_button');
    add_action('comment_form_before_fields', 'alt_comment_login',1,0);
    add_action('alt_comment_login', 'fp_comm_login_button');
    add_action('comment_form_before_fields', 'comment_user_details_begin',2,0);
    add_action('comment_form_after_fields', 'comment_user_details_end',20,0);
    add_action('comment_post','fp_comm_add_meta', 10, 1);
    add_filter('pre_comment_on_post','fp_comm_fill_in_fields');
}

