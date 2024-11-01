<?php

/* ************
** WP APP SETUP
** ***********/
// use App ID provided on Start A Fire > Developers
$SAF_APP_ID = "dqykgMKbpLkvWQzqGnCrIY8jcu8uEilDnzFeHOG0";
$SAF_APP = "https://startafire.com/app/token/" . $SAF_APP_ID;
$BASE_URL = "https://api.startafire.com/";
$SAF_URL_SHORTENER = "stfi.re";

/* *******
** TESTING
** ******/

// for testing, remove line below for production (localhost:8888 cannot be turned into fire)
// $THIS_DOMAIN = "jonathan-levi.com";

// logs for testing
// set both WP_DEBUG and WP_DEBUG_LOG in wp-config.php to true, writes to log file in ../wp-content

function saf_write_to_log($toLog) {
	if (WP_DEBUG === true) {
		if (is_array($toLog) || is_object($toLog)) {
			error_log(print_r($toLog, true));
		} else {
			error_log($toLog);
		}
	}
}

/* **************
** EXTERNAL FILES 
** *************/

function saf_register_external_files($page) {
	global $SAF_URL_SHORTENER;
	global $EXCLUDED_DOMAINS;
	global $USER_ACTIVE_TOKEN;
	global $BASE_URL;

	saf_loadWPoptionData();

	// register external files below
	define('saf_plugin_path', plugin_dir_url( __FILE__ ));
	wp_register_style('saf_custom_css', saf_plugin_path . 'saf-plugin-v1.css');
	wp_register_script('saf_custom_js', saf_plugin_path . 'saf-post-v1.js');
	wp_register_script('saf_settings_js', saf_plugin_path . 'saf-settings-v1.js');

	// enqueue external files below

	wp_enqueue_style('saf_custom_css');

	wp_enqueue_script('jquery');

	if (saf_is_token_valid($USER_ACTIVE_TOKEN)) {
		wp_localize_script('saf_custom_js', 'SAF_URL_SHORTENER', $SAF_URL_SHORTENER);
		wp_localize_script('saf_custom_js', 'SAF_EXCLUDED_DOMAINS', $EXCLUDED_DOMAINS);
		wp_enqueue_script('saf_custom_js');
	}

	if ('settings_page_startafire-settings-menu' == $page) {
		wp_localize_script( 'saf_settings_js', 'USER_ACTIVE_TOKEN', $USER_ACTIVE_TOKEN);
		wp_localize_script( 'saf_settings_js', 'BASE_URL', $BASE_URL);
		wp_enqueue_script('saf_settings_js');	
	}
}

add_action('admin_enqueue_scripts', 'saf_register_external_files');

/* ****************
** GLOBAL VARIABLES 
** ***************/
$EXCLUDED_DOMAINS = array();
$USER_DATA = array();
$USER_ACTIVE_TOKEN;
$SAF_FIRST_ACTIVATION;
$SAF_LINKS_CHANGED_IN_POSTS;
$SAF_COUNT_FIRES;
$THIS_DOMAIN = get_site_url();
$SAF_LOGIN_URL = admin_url('options-general.php?page=startafire-settings-menu');

/* ****************
** HELPER FUNCTIONS
** ***************/

// retrieve token from local database, user data and excluded domains from API
function saf_loadWPoptionData() {
	global $USER_ACTIVE_TOKEN;
	global $USER_DATA;
	global $SAF_FIRST_ACTIVATION;
	global $SAF_LINKS_CHANGED_IN_POSTS;
	global $EXCLUDED_DOMAINS;

	try {
		$USER_ACTIVE_TOKEN = get_option('saf_token');
		$SAF_FIRST_ACTIVATION = get_option('saf_first_activation');
		$SAF_LINKS_CHANGED_IN_POSTS = get_option('saf_links_changed_in_posts');	
	} catch (Exception $e) {
		saf_write_to_log('83724189' + $e);
		return false;
	}

	saf_set_user_data();
	saf_set_excluded_domains_list();

	if (empty($USER_DATA) || empty($USER_ACTIVE_TOKEN)) {
		return false;
	}

	return true;
}

// generates error messages, returns BOOL
function saf_is_token_valid($token) {
	global $BASE_URL;

	$service_url = $BASE_URL . 'user/get/?access_token=' . $token;
	try {
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($curl, CURLOPT_GET, true);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('0990532' + $e);
		return false;
	}
	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		return false;
	}
	return true;
}

// get site info
function saf_get_site_info() {
	$activeAndInstalledPlugins = array();
	$siteInfo = array();

	try {
		$siteInfo["phpVersion"] = PHP_VERSION;
		$siteInfo["wpVersion"] = get_bloginfo('version');
		$siteInfo["siteUrl"] = get_bloginfo('url');

		$installedPlugins = get_plugins();
		$activePlugins = get_option('active_plugins');
		foreach ($installedPlugins as $key => $value) {
			for ($i=0; $i < count($activePlugins); $i++) { 
				if (strpos(strtolower($activePlugins[$i]),strtolower($value["Name"])) !== false) {
					$currentPlugin = array();
					$currentPlugin[0] = $value["Name"];
					$currentPlugin[1] = $value["PluginURI"];
					$currentPlugin[2] = $value["Version"];
					array_push($activeAndInstalledPlugins, $currentPlugin);
				}
			}
		}
	} catch (Exception $e) {
		saf_write_to_log('108362' + $e);
		return array();
	}

	$response = array();
	array_push($response, $activeAndInstalledPlugins);
	array_push($response, $siteInfo);

	return $response;
}

// send event to Mixpanel
function saf_new_tracking_event($event,$info='') {
	global $USER_ACTIVE_TOKEN;
	global $BASE_URL;
	$service_url = $BASE_URL . 'tracking?access_token=' . $USER_ACTIVE_TOKEN;
	$arrayToSend = array(
		"source" => "wordpress_plugin",
		"event" => $event
	);

	if ($info != '') {
		$arrayToSend['source'] = "wordpress_plugin_" . $info;
	}

	try {
		$curl = curl_init($service_url);
		$curl_post_data = $arrayToSend;
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('7349182' + $e);
		return;
	}

	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		saf_write_to_log('090982 could not send Mixpanel event');
		return;
	}
}

// open JavaScript popup to get the user-specific Wordpress app token
function saf_open_login_popup($handler) {
	global $SAF_APP;
	?>
	<script type="text/javascript">
	var $ = jQuery;
	$(document).ready(function() {
		$(<?php echo json_encode($handler)?>).click(function() {
			window.open(<?php echo json_encode($SAF_APP) ?>, "_blank", "toolbar=yes,scrollbars=yes,resizable=yes,top=500,left=500,width=1020,height=700");
		});
	});
	</script>
	<?php
}

// set user account information in $USER_DATA
// return NULL if error
function saf_set_user_data() {
	global $USER_ACTIVE_TOKEN;
	global $USER_DATA;
	global $BASE_URL;

	$service_url = $BASE_URL . 'user/get/?access_token=' . $USER_ACTIVE_TOKEN;

	try {
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($curl, CURLOPT_GET, true);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('212723' + $e);
		$USER_DATA = NULL;
		return;
	}
	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		$USER_DATA = NULL;
		return;
	}

	$USER_DATA = $response["userDetails"];
}

// set excluded domains list in $EXCLUDED_DOMAINS
// return NULL if error
function saf_set_excluded_domains_list() {
	global $USER_ACTIVE_TOKEN;
	global $EXCLUDED_DOMAINS;
	global $BASE_URL;

	$service_url = $BASE_URL . 'user/getexcludeddomains?access_token=' . $USER_ACTIVE_TOKEN;

	try {
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('8629874' + $e);
		$EXCLUDED_DOMAINS = NULL;
		return;
	}
	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		$EXCLUDED_DOMAINS = NULL;
		return;
	}

	$EXCLUDED_DOMAINS = $response["domains"];
}

// on plugin login, add current domain to excluded domains list
// return wp_die() if error removed
function saf_set_this_domain_to_excluded() {
	global $USER_ACTIVE_TOKEN;
	global $BASE_URL;
	global $THIS_DOMAIN;

	$service_url = $BASE_URL . 'user/addexcludeddomains?access_token=' . $USER_ACTIVE_TOKEN;
	try {
		$curl = curl_init($service_url);
		$curl_post_data = array(
			"domain" => $THIS_DOMAIN
		);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('87521' + $e);
		return false;
	}
	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		return false;
	}

	return true;
}

// if user is not logged in, on activation, display admin notice
function saf_show_activation_prompt() {
	global $SAF_LOGIN_URL;
	global $SAF_FIRST_ACTIVATION;

	$SAF_FIRST_ACTIVATION = get_option('saf_first_activation');

	if (!$SAF_FIRST_ACTIVATION) {
	?>
		<div class="notice notice-success saf_blue_notice">
			<p><b>Start A Fire successfully installed.</b></p>
			<p>Please activate the plugin by <a href="<?php echo esc_url($SAF_LOGIN_URL);?>">connecting your account.</a></p>
		</div>
	<?php
	}
}

// if current screen is the plugins page, saf_show_activation_prompt()
function saf_trigger_screen_actions($screen) {
	$currentScreen = $screen->base;
	if ($currentScreen == "plugins") {
		add_action('admin_notices', 'saf_show_activation_prompt');
	}
}

add_action('current_screen', 'saf_trigger_screen_actions');

/* ************************** 
** CORE: create fires on save
** *************************/

// on save (publish, draft, update) actions, check all links in current post:
// a. if link is fire and has saf_no_replace class, convert to original link
// b. if link is not fire, convert to fire
function saf_check_post_links($post_id) {
	global $USER_ACTIVE_TOKEN;
	global $EXCLUDED_DOMAINS;
	global $SAF_URL_SHORTENER;
	// exit if post is autosaved or moved to trash
	if(wp_is_post_autosave($post_id) || get_post_status($post_id) == 'trash' || get_post_status($post_id) == 'inherit') {
		return;
	}
	// if post is a revision, get real post ID
	if ($parent_id = wp_is_post_revision($post_id)) {
		$post_id = $parent_id;
	}
	// load variables
	if (!saf_loadWPoptionData()) {
		return;
	}
	// unhook this function so it doesn't loop infinitely
	// https://codex.wordpress.org/Plugin_API/Action_Reference/save_post
	remove_action('save_post', 'saf_check_post_links', 10);

	$content = get_post_field('post_content', $post_id);

	// save content to variable as backup
	$previousContent = $content;

	// find links with regex
	$linkPattern = '/(?<=\<)a.*?(?=\>)/';
	preg_match_all($linkPattern, $content, $foundLinks);
	$linksInPost = $foundLinks[0];

	try {
		$linkCounter  = -1;
		foreach($linksInPost as $link) { 
			$linkCounter++;

			// array holds attributes for current link
			$currentLink = array();

			// get class attribute
			$classPattern = '/(?<=class=("|\'))[^"\']+?(?=("|\'))/';
			if (preg_match($classPattern, $link, $classMatches) !== false) {
				if (!empty($classMatches)) {
					$currentLink['class'] = $classMatches[0];
				} else {
					$currentLink['class'] = '';
				}
			} else {
				throw new Exception('54665 $currentLink regex error');
			}

			// get href attribute (also used in saf_regex_callback)
			$hrefPattern = '/(?<=href=("|\'))[^"\']+?(?=("|\'))/';
			if (preg_match($hrefPattern, $link, $hrefMatches) !== false) {
				if (!empty($hrefMatches)) {
					$currentLink['url'] = $hrefMatches[0];
				} else {
					throw new Exception('529100 $currentLink empty url');
				}
			} else {
				throw new Exception('214554 $currentLink regex error');
			}

			if (!is_string($currentLink['url'])) {
				throw new Exception('456436 $currentLink url is not a string');
			}

			// if a tag does not contain url, skip link
			if (empty($currentLink['url'])) {
				continue;
			}

			// check if link is in excluded domains list
			$domainIsExcluded = false;
			foreach ($EXCLUDED_DOMAINS as $key => $domain) {
				if (strpos($currentLink['url'], $domain) !== false) {
					$domainIsExcluded = true;
				}
			}

			// if link is excluded, skip link
			if ($domainIsExcluded) {
				continue;	
			}

			// get original link or fire
			$url;

			if (strpos($currentLink['url'], $SAF_URL_SHORTENER) !== false) {
				if (strpos($currentLink['class'], 'saf_no_replace') !== false) {
					$url = saf_get_original_link($currentLink['url']);

					// if there was an error making the fire/retrieving the original, skip link
					if (is_null($url) || !$url['success'] || $url['original_url'] === '') {
						continue;
					}

					try {
						$currentContentModified = preg_replace_callback('/' . preg_quote($linksInPost[$linkCounter], '/') . '/', function ($linkMatches) use ($url,$hrefPattern) {
							// current a tag
							$firstMatch = $linkMatches[0];
							// change link in href attribute
							$firstMatch = preg_replace($hrefPattern, $url['original_url'], $firstMatch);
							// no need to handle error: if regex not found, orginal link is returned
							return $firstMatch;
						}, $content);	
					} catch (Exception $e) {
						saf_write_to_log('865217' + $e);
						throw new Exception('6541 $currentContentModified could not replace link with fire');
					}

					if (is_null($currentContentModified) || $currentContentModified === '') {
						throw new Exception('82936 parsing error 1');
					}

					$content = $currentContentModified;

				} else {
					continue;
				}
			} else {
				if (!(strpos($currentLink['class'], 'saf_no_replace') !== false)) {
					$url = saf_create_new_link($currentLink['url'],'make_fire_on_post');

					// if there was an error making the fire/retrieving the original, skip link
					if (is_null($url) || !$url["success"] || $url['url'] == "") {
						continue;
					}

					try {
						$currentContentModified = preg_replace_callback('/' . preg_quote($linksInPost[$linkCounter], '/') . '/', function ($linkMatches) use ($url,$hrefPattern) {
							// current a tag
							$firstMatch = $linkMatches[0];
							// change link in href attribute
							$firstMatch = preg_replace($hrefPattern, $url['url'], $firstMatch);
							// no need to handle error: if regex not found, orginal link is returned
							return $firstMatch;
						}, $content);	
					} catch (Exception $e) {
						saf_write_to_log('86219742' + $e);
						throw new Exception('76969 $currentContentModified could not replace fire with original link');
					}

					if (is_null($currentContentModified) || $currentContentModified === '') {
						throw new Exception('91265 parsing error 2');
					}

					$content = $currentContentModified;

				} else {
					continue;
				}
			}
		}

		// if new content is empty for any reason, replace with old
		if ($content === '') {
			$content = $previousContent;
			saf_new_tracking_event('wordpress_plugin_content_not_replaced_with_fires_content_empty');
			throw new Exception ('8162489 CRITICAL CONTENT EMPTY');
		}

	} catch (Exception $e) {
		saf_write_to_log('11111111' + $e);
		// report error if initial content was not empty
		if ($content !== '') {
			saf_new_tracking_event('wordpress_plugin_content_not_replaced_with_fires_exception_raised');
		}
		// reset post content
		$content = $previousContent;
	}

	// update the post, which calls save_post again
	wp_update_post(array('ID' => $post_id, 'post_content' => $content));
	// re-hook this function
	add_action('save_post', 'saf_check_post_links', 10);
}

// get original link from existing fire
function saf_get_original_link($link) {
	global $USER_ACTIVE_TOKEN;
	global $BASE_URL;
	// replace link with valid token below
	$service_url = $BASE_URL . 'fires/getfiredatabyhash?access_token=' . $USER_ACTIVE_TOKEN . '&target=' . $link;

	try {
		$curl = curl_init($service_url);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('868248' + $e);
		return null;
	}

	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		saf_write_to_log('9292923 could not get original link');
		return null;
	}

	return $response;
}

// make fire from non-fire link
function saf_create_new_link($link, $subsource) {
	global $USER_ACTIVE_TOKEN;
	global $BASE_URL;
	// replace link with valid token below
	$service_url = $BASE_URL . 'fires/create?access_token=' . $USER_ACTIVE_TOKEN;

	try {
		$curl = curl_init($service_url);
		$curl_post_data = array(
			"target" => $link,
			"source" => "wordpress_plugin_" . $subsource
		);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_post_data);
		$curl_response = curl_exec($curl);
		curl_close($curl);
	} catch (Exception $e) {
		saf_write_to_log('862348' + $e);
		return null;
	}

	// parse response as array, extract url
	$response = json_decode($curl_response, true);
	if (!$response["success"]) {
		saf_write_to_log('817351 could not get fire');
		return null;
	}

	return $response;
}

add_action('save_post', 'saf_check_post_links', 10);

// turn all non-fire links in blog posts into fires
function saf_force_replace_all_links() {
	try {
		// get all (-1) posts 
		$allPosts = get_posts(-1);
		// keep IDs
		$allPosts = wp_list_pluck($allPosts, 'ID');

		foreach ($allPosts as $post_id) {
			saf_check_post_links($post_id);
		}	
	} catch (Exception $e) {
		saf_write_to_log('914126' + $e);
	}
}

add_action('saf_launch_force_replace_all_links', 'saf_force_replace_all_links');

// fired on button click in Settings, schedules cron job
function saf_schedule_force_replace_all_links() {
	// WP limit: cannot schedule the same job within 10min
	// https://codex.wordpress.org/Function_Reference/wp_schedule_single_event
	wp_schedule_single_event(time(), 'saf_launch_force_replace_all_links');
	saf_new_tracking_event('wordpress_plugin_links_all_posts_replaced');
}

/* **********************************************
** COUNT FIRES: Display feedback on new post page
** *********************************************/

// if current screen is a post:
// if token is not valid, display error message
// if token is valid, count and display fire number in post
function saf_display_number_of_fires(){
	global $USER_ACTIVE_TOKEN;
	global $SAF_LOGIN_URL;
	global $SAF_COUNT_FIRES;
	global $SAF_URL_SHORTENER;

	saf_loadWPoptionData();

	if (!saf_is_token_valid($USER_ACTIVE_TOKEN)) {
	?>
	<hr>
	<div class="misc-pub-section">
		<div class="saf_make_fire saf_fire_misc_pub_section saf_fire_red"></div>
		<span class="saf_pub_section_text">Failed to add badge to your links.
			<b>Login to <a href="<?php echo esc_url($SAF_LOGIN_URL);?>">Start A Fire</a> to connect your account.</b>
		</span>
	</div>
	<?php
	} else {
		$SAF_COUNT_FIRES = 0;
		$post_id = get_the_ID();
		// if post is a revision, get real post ID
		if ($parent_id = wp_is_post_revision($post_id)) {
			$post_id = $parent_id;
		}
		$content = get_post_field('post_content', $post_id);
		$arr = preg_split('/ /',$content);
		// count fires
		for ($i=0; $i < count($arr); $i++) { 
			$value = $arr[$i];
			if (strpos($value, 'href="') !== false) {
				$tag = explode("\"", $value);
				// check if string is fire
				if (strpos($tag[1], $SAF_URL_SHORTENER) == true) {
					$SAF_COUNT_FIRES++;
				}
			}
		}
		// singular/plural for link(s) text
		if ($SAF_COUNT_FIRES==1) {
			$saf_link_counter_text = 'link';
		} else {
			$saf_link_counter_text = 'links';	
		}
	?>
	<hr>
	<div class="misc-pub-section">
		<div class="saf_make_fire saf_blue_fire saf_fire_misc_pub_section"></div>
		<span class="saf_pub_section_text">Post contains <b><span class="saf_replaced_fires_number"><?php echo $SAF_COUNT_FIRES ?></span> <?php echo $saf_link_counter_text ?></b> with your badge</span>
	</div>
	<?php
	}
}

add_action('post_submitbox_misc_actions', 'saf_display_number_of_fires');

/* ********************
** PLUGIN SETTINGS Page
** *******************/

// add inline link to SAF plugin settings from 'Plugins' page
add_filter('plugin_action_links_startafire/saf-trigger.php' , 'saf_add_links_to_settings');

function saf_add_links_to_settings($links) {
	global $SAF_LOGIN_URL;
	$links[] = '<a href="'. esc_url($SAF_LOGIN_URL) .'">Settings</a>';
	return $links;
}

// admin_init is first function to fire when user accesses admin panel
add_action('admin_init', 'saf_plugin_settings');

// registers SAF settings data fields: token, activation, fire count
// does not require nonces, has built in security
function saf_plugin_settings() {
	register_setting( 'saf-settings-group', 'saf_token', 'saf_sanitize_token');
	register_setting( 'saf-settings-group', 'saf_first_activation' );
	register_setting( 'saf-settings-group', 'saf_links_changed_in_posts' );
}

// check token validity on 'Activate' in login screen, return token
// if token is a. empty or b. invalid, display error message
// TODO: alerts show twice after plugin activation
function saf_sanitize_token($data) {
	if (empty($data)) {
		add_settings_error('saf_token', 'saf_token_error', 'Please provide a token. <a id="error_loginToSaf" class="saf_link">Get it from Start A Fire</a>.', 'error');
	} elseif(!saf_is_token_valid($data)) {
		add_settings_error('saf_token', 'saf_token_error', 'This token is not valid. Please try again or email hello@startafire.com for help.', 'error');
	} else {
		add_settings_error('saf_token', 'saf_token_error_welcome', 'Welcome to Start a Fire.', 'updated');
		return $data;
	}
}

// hook SAF plugin menu after WP settings are loaded
add_action('admin_menu', 'saf_display_menu');

function saf_display_menu() {
	add_options_page( 'Start A Fire - Settings', 'Start A Fire', 'manage_options', 'startafire-settings-menu', 'saf_display_router' );
}

// route requests to saf_display_login() or saf_display_settings()
// router is always called when the user accesses the SAF plugin settings
function saf_display_router() {
	global $USER_ACTIVE_TOKEN;
	global $USER_DATA;
	global $SAF_FIRST_ACTIVATION;
	global $SAF_LINKS_CHANGED_IN_POSTS;
	global $SAF_LOGIN_URL;

	// check for user permissions
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}

	// force logout, clean options, register settings
	if (isset($_POST['saf_switch_account'])) {
		update_option('saf_first_activation', false, true);
		update_option('saf_links_changed_in_posts', false, true);
		update_option('saf_token', '', true );
		return saf_display_login();
	}

	// if info is missing, go to login
	if (!saf_loadWPoptionData()) {
		return saf_display_login();
	}

	// by default, current domain is set to excluded domain.
	// user can remove domain from excluded list
	if (!$SAF_FIRST_ACTIVATION) {
		saf_set_this_domain_to_excluded();
		saf_new_tracking_event('wordpress_plugin_user_activated',json_encode(saf_get_site_info()));
	}

	// check for force replace all links
	// tracking event in saf_schedule_force_replace_all_links() 
	if (isset($_POST['saf_btn_replace_all_links']) && !$SAF_LINKS_CHANGED_IN_POSTS) {
		saf_schedule_force_replace_all_links();
	}

	return saf_display_settings();
}

// display login screen
// take token, on submit load saf_display_router()
function saf_display_login() {
	?>
	<div class="wrap">
		<h2>Start A Fire</h2>
		<p class="saf_login_info">Please connect your Start A Fire account in order to activate this plugin. <a id="loginToSaf" class="saf_link"><b>Get API token</b></a></p>
		<form method="post" action="options.php">
			<?php settings_fields('saf-settings-group'); ?>
			<input class="saf_login_form_input" type="text" name="saf_token" size="50" autocomplete="off" placeholder="Paste your API token here" value="<?php echo esc_attr(get_option('saf_token')); ?>" />
			<span class="saf_inline">
			<?php submit_button('Activate','primary','saf_token_btn'); ?>
			</span>
		</form>
	</div>

	<?php
	saf_open_login_popup('#loginToSaf');
	saf_open_login_popup('#error_loginToSaf');
}

// display SAF plugin settings
// loads saf-settings.js that contains AJAX calls to the API
function saf_display_settings() {
	global $USER_DATA;
	global $USER_ACTIVE_TOKEN;
	global $EXCLUDED_DOMAINS;
	global $SAF_FIRST_ACTIVATION;
	global $SAF_LINKS_CHANGED_IN_POSTS;
	global $BASE_URL;

	// if this is the first login, display welcome message
	if (!$SAF_FIRST_ACTIVATION) {
		update_option('saf_first_activation', true, true);
	    ?>
	    <div class="notice notice-success saf_notice_welcome is-dismissible">
	        <p>Your account <b><?php echo($USER_DATA["displayName"]); ?></b> has been connected successfully.</p>
	        <p>Your badge will be automatically added to external links in future published posts.</p>
	    </div>
	    <?php
	}

	// if this is loaded after adding badge to all links, display loading message
	if (isset($_POST['saf_btn_replace_all_links']) && !$SAF_LINKS_CHANGED_IN_POSTS) {
		update_option('saf_links_changed_in_posts', true, true);
		$SAF_LINKS_CHANGED_IN_POSTS = true;
		?>
		<div class="notice notice-success is-dismissible">
			<p>Start A Fire is adding your badge to links on this site.</p>
			<p>Depending on the number of your posts, it may take several minutes to reflect link changes.</p>
		</div>
		<?php
	}
	?>

	<div class="wrap">
		<h2 class="saf_headings">Start A Fire</h2>

		<div class="profile_wrapper">
			<div class="saf_profile_description">
				<img class="saf_profile_image" src="<?php echo($USER_DATA["imageUrl"]); ?>">
			</div>
			<div class="saf_profile_description saf_profile_description_right">
				<!-- request handled by router, redirects to login page -->
				<form method="post" action="options-general.php?page=startafire-settings-menu">
					<!-- <input class="button button-primary saf_btn_submit" type="submit" name="saf_btn_replace_all_links" value="Add badge to all links in previous posts"> -->
					<h4>Connected as <?php echo($USER_DATA["displayName"]); ?> <input class="saf_switch_account_btn" type="submit" name="saf_switch_account" value="(Switch account)"></input></h4>
				</form>
				<p>Your badge will be automatically added to external links in future published posts.</p>
				<p class="saf_descr_link_container"><a href="http://startafire.com/?utm_source=wordpress_plugin" target="_blank">View your links stats</a></p>
			</div>
		</div>

		<section class="saf_section">
			<h2>Excluded Domains</h2>
			<div class="saf_text_block">
				<p>We will not add your badge to any link in the following domains.</p>
			</div>
			<div class="saf_excluded_domains_container">
				<div class="saf_excluded_domains_input">
					<input type="text" id="saf_excluded_domains" placeholder="Add new excluded domain" />
					<span class="saf_inline">
						<button class="button button-primary saf_btn_submit" id="saf_add_domain">Add Domain</button>
					</span>
				</div>
				<div class="wrap saf_table_wrap">
					<table class="widefat saf_excludeddomains_table">
						<!-- saf-settings.js controls table content and link submission -->
					</table>
				</div>
			</div>
		</section>

		<section class="saf_section">
			<h2>Add badge to all external links in previous posts</h2>
			<div class="saf_text_block">
				<p>By default, Start A Fire will add your badge to all external links in future published posts.</p>
				<p>If you would like to add your badge to all links in previous posts, click the button below.</p>
			</div>
			<!-- CUSTOM FORM: not managed by WP Settings API -->
			<!-- no NONCE required, router checks user permissions -->
			<form class="saf_tooltip" method="post" action="options-general.php?page=startafire-settings-menu">
				<?php
				if ($SAF_LINKS_CHANGED_IN_POSTS) {
					?>
					<input class="button saf_btn_submit" type="submit" name="saf_btn_replace_all_links" value="Add badge to all links in previous posts" disabled="true">
					<span class='tooltiptext tooltiptext_big'>All previous posts updated</span>
					<?php
				} else {
					?>
					<input class="button button-primary saf_btn_submit" type="submit" name="saf_btn_replace_all_links" value="Add badge to all links in previous posts">
					<?php
				}
				?>
			</form>
		</section>
	</div>
	<?php
}
?>