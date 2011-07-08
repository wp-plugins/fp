<?php
/**
 * FacePress OAuth
 */
function fp_oauth() {
	$options = fp_options();
	if( !fp_ready() )
		return false;
	
	$url = 'https://graph.facebook.com/oauth/authorize';
	
	$appId = fp_options('appId');
	$secret = fp_options('secret');
	$redirect_uri = get_bloginfo('url') . '/oauth/facebook';
	
	if( isset( $_GET['code'] ) ) {
	
		if( !isset( $_GET['error_reason'] ) ) {
	
			$url = 'https://graph.facebook.com/oauth/access_token';
		
			$url = add_query_arg('client_id', $appId, $url);
			$url = add_query_arg('redirect_uri', $redirect_uri, $url);
			$url = add_query_arg('client_secret', $secret, $url);
			$url = add_query_arg('code', $_GET['code'], $url);
		
			$token = wp_remote_get($url);
			
			$t = wp_parse_args( $token['body'], array(
					'access_token' => false, 'expires' => false
			));
			
			if( isset($t['access_token']) ) {
				
				// Test
				$test = wp_remote_get('https://graph.facebook.com/me?access_token='.$t['access_token']);
				$test = json_decode($test['body']);
				
				if( isset($test->id) ) {
				
					unset($_SESSION['scope']);
				
					$_SESSION['fb-connected'] = true;
					$_SESSION['fp_access_token'] = $t;
					$_SESSION['fb-me'] = $test;
				
				}
			}
		}
		
		$back = isset( $_SESSION['fp_callback'] ) ? $_SESSION['fp_callback'] : get_bloginfo('url') . '/';
		
		if( isset( $_SESSION['fp_callback_action'] ) ) {
			do_action('fp_' . $_SESSION['fp_callback_action']);
		}
		
		wp_redirect($back);
		exit;
		
	} elseif( !isset( $_GET['location'] ) && !isset( $_GET['action'] ) ) {
		die(__('Error: no location or action is defined!'));
	}
	
	$url = add_query_arg('client_id', fp_options('appId'), $url);
	$url = add_query_arg('redirect_uri', get_bloginfo('url') . '/oauth/facebook', $url);
	
	if( isset( $_GET['perms'] ) && !empty($_GET['perms']) ) {
		$url = add_query_arg('scope', $_GET['perms'], $url);
	}
	
	$_SESSION['fp_callback'] = isset($_GET['location']) ? $_GET['location'] : get_bloginfo('url') . '/';
	
	if( isset($_GET['action']) )
		$_SESSION['fp_callback_action'] = $_GET['action'];
		
	$url = apply_filters('fb_oauth_url', $url);

	wp_redirect($url);
	exit;
}
add_action('oauth_start_facebook', 'fp_oauth');

