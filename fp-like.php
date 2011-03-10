<?php
/**
 * FacePress Like Button
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
		'layout' => $options['like_layout'],
		'action' => $options['like_action'],
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
	add_settings_section('fp_like', __('Like Button Settings', 'fp'), 'fp_like_section_callback', 'fp');
	add_settings_field('fp_like_position', __('Like Button Position', 'fp'), 'fp_like_position', 'fp', 'fp_like');
	add_settings_field('fp_like_layout', __('Like Button Layout', 'fp'), 'fp_like_layout', 'fp', 'fp_like');
	add_settings_field('fp_like_action', __('Like Button Action', 'fp'), 'fp_like_action', 'fp', 'fp_like');
	add_settings_field('fp_like_css', __('Like Button CSS', 'fp'), 'fp_like_css', 'fp', 'fp_like');
}

function fp_like_section_callback() {
	echo '<p>'.__('Choose where you want the like button added to your content.', 'fp').'</p>';
}

function fp_like_position() {
	$options = fp_options();
	if (!$options['like_position']) $options['like_position'] = 'manual';
	?>
	<ul>
	<li><label><input type="radio" name="fp_options[like_position]" value="before" <?php checked('before', $options['like_position']); ?> /> <?php _e('Before the content of your post', 'fp'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_position]" value="after" <?php checked('after', $options['like_position']); ?> /> <?php _e('After the content of your post', 'fp'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_position]" value="both" <?php checked('both', $options['like_position']); ?> /> <?php _e('Before AND After the content of your post', 'fp'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_position]" value="manual" <?php checked('manual', $options['like_position']); ?> /> <?php _e('Manually add the button to your theme or posts (use the get_fblike function in your theme)', 'fp'); ?></label></li>
	</ul>
<?php 
}

function fp_like_layout() {
	$options = fp_options();
	if (!$options['like_layout']) $options['like_layout'] = 'standard';
	?>
	<ul>
	<li><label><input type="radio" name="fp_options[like_layout]" value="standard" <?php checked('standard', $options['like_layout']); ?> /> <?php _e('Standard', 'fp'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_layout]" value="button_count" <?php checked('button_count', $options['like_layout']); ?> /> <?php _e('Button with counter', 'fp'); ?></label></li>
	</ul>
<?php 
}

function fp_like_action() {
	$options = fp_options();
	if (!$options['like_action']) $options['like_action'] = 'like';
	?>
	<ul>
	<li><label><input type="radio" name="fp_options[like_action]" value="like" <?php checked('like', $options['like_action']); ?> /> <?php _e('Like', 'fp'); ?></label></li>
	<li><label><input type="radio" name="fp_options[like_action]" value="recommend" <?php checked('recommend', $options['like_action']); ?> /> <?php _e('Recommend', 'fp'); ?></label></li>
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

