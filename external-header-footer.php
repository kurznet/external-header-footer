<?php
/*
 Plugin Name: External Header Footer
 Plugin URI: https://github.com/kurznet/external-header-footer
 Description: Exposes the header and footer of the website as individual files, allowing for external consumption (for third parties sites that want a similar design style).
 Author: Kurznet
 Version: 1.0.3
 Author URI: https://kurznet.com/
*/

// Add a new rewrite rule that points to our exposed header and footer.
function ehf_add_rewrite_rules_parameters() {
	add_rewrite_tag('%ehf_template%','([^&]+)');

	add_rewrite_rule( '^external-header-footer/([header|footer|demo]+)/?$', 'index.php?ehf_template=$matches[1]', 'top');
}
add_action('init', 'ehf_add_rewrite_rules_parameters', 1);

// Handle requests that contain the "ehf_template" parameter.
function ehf_parse_request( &$wp ) {
	if ( array_key_exists( 'ehf_template', $wp->query_vars ) ) {
        global $wp_query;

        // If we've disallowed output, exit immediately.
		if ( ( (int) get_option('ehf_expose_header_and_footer', 0) ) == 0 )  {
			return;
		}

        switch ( $wp->query_vars['ehf_template'] ) {
        	case 'header':
        		// Execute any actions that have been coded into the theme/other plug-ins to run before the footer is output.
				do_action('external_header_footer_pre_header');

				// Capture the header of the website to a string.
				ob_start();
			    get_header();
			    $str_output = ob_get_contents();
			    ob_end_clean();

			    // set current page item
		        if ( ( (int) get_option('ehf_external_current_page', 0) ) != 0 ) {
			        $current_item = get_option('ehf_external_current_page');
			        $re = '/class="(.*?)menu-item-'.$current_item.'(.*?)"/m';
			        $subst = 'class="$1menu-item-'.$current_item.' current_page_item"';
			        $str_output = preg_replace($re, $subst, $str_output);
		        }

			    // If we're forcing use of absolute URLs, filter the output of the header through a function.
				if ( ( (int) get_option('ehf_force_use_of_absolute', 0) ) == 1 ) {
					$str_output = ehf_do_force_absolute_urls($str_output);
				}

				// If we're forcing use of HTTPS, filter the output of the header through a function.
				if ( ( (int) get_option('ehf_force_use_of_https', 0) ) == 1 ) {
					$str_output = ehf_do_force_https($str_output);
				}

				// Output the header.
				echo $str_output;
				exit;
        		break;
        	case 'footer':
    			// Execute any actions that have been coded into the theme/other plug-ins to run before the footer is output.
				do_action('external_header_footer_pre_footer');

				// Capture the footer of the website to a string.
				ob_start();
				get_footer(); 
				$str_output = ob_get_contents();
			    ob_end_clean();

			    // If we're forcing use of absolute URLs, filter the output of the header through a function.
				if ( ( (int) get_option('ehf_force_use_of_absolute', 0) ) == 1 ) {
					$str_output = ehf_do_force_absolute_urls($str_output);
				}

				// If we're forcing use of HTTPS, filter the output of the footer through a function.
				if ( ( (int) get_option('ehf_force_use_of_https', 0) ) == 1 ) {
					$str_output = ehf_do_force_https($str_output);
				}

				// Output the footer.
				echo $str_output;
				exit;
				break;
			case 'demo':
				// Output the external header to the page.
				ehf_output_external_header();
				?>

				<p>
				Right here is where content is displayed; you should see the header and footer of the external website above and below this text.
				</p>

				<?php 
				// Output the external footer to the page.
				ehf_output_external_footer();
				exit;
				break;
        }
    }
}
add_action('parse_request', 'ehf_parse_request');

/**
 * Output the contents of the external header wherever the function ehf_output_external_header() is called.
 *
 * @return void
 */
function ehf_output_external_header() {
	if ( false === ( $str_external_header = get_transient('ehf_external_header_url') ) ) {
		// Get the URL to try to get to retrieve the header from. If it's blank, exit immediately.
		$ehf_external_header_url = get_option('ehf_external_header_url', '');
		if ( strlen($ehf_external_header_url) == 0 ) {
			return;
		}

		// Retrieve the external header.
		$arr_header = wp_remote_get($ehf_external_header_url);
		$str_external_header = $arr_header['body'];

		// Save the contents of the retrieved external header to the local cache (via the Transients API).
		$ehf_external_cache_expiry = get_option('ehf_external_cache_expiry', '60');
		set_transient('ehf_external_header_url', $str_external_header, $ehf_external_cache_expiry);
	}

	echo $str_external_header;
}

/**
 * Output the contents of the external footer wherever the function ehf_output_external_footer() is called.
 *
 * @return void
 */
function ehf_output_external_footer() {
	if ( false === ( $str_external_footer = get_transient('ehf_external_footer_url') ) ) {
		// Get the URL to try to get to retrieve the footer from. If it's blank, exit immediately.
		$ehf_external_footer_url = get_option('ehf_external_footer_url', '');
		if ( strlen($ehf_external_footer_url) == 0 ) {
			return;
		}

		// Retrieve the external footer.
		$arr_header = wp_remote_get($ehf_external_footer_url);
		$str_external_footer = $arr_header['body'];

		// Save the contents of the retrieved external footer to the local cache (via the Transients API).
		$ehf_external_cache_expiry = get_option('ehf_external_cache_expiry', '60');
		set_transient('ehf_external_footer_url', $str_external_footer, $ehf_external_cache_expiry);
	}

	echo $str_external_footer;
}

/**
 * Called when we wish to force use of HTTPS on links to the WordPress site domain.
 *
 * @param string $str_output The string within which to force relative to root links to be absolute
 * @return string
 */
function ehf_do_force_absolute_urls( $str_output ) {
	// Get this WordPress website's home URL.
	$url_home 	= home_url( '/' );

	// Replace SRC and HREF attributes that are absolute to root.
	$str_output = preg_replace('/(src|href)=(\'|")\//i', '$1=$2' . $url_home, $str_output);

	return $str_output;
}

/**
 * Called when we wish to force use of HTTPS on links to the WordPress site domain.
 *
 * @param string $str_output The string within which to force links to this WordPress site's domain to HTTPS
 * @return string
 */
function ehf_do_force_https( $str_output ) {
	// Get this WordPress website's home URL in HTTPS and HTTP.
	$url_https 	= home_url( '/', 'https' );
	$url_http 	= home_url( '/', 'http' );

	return str_replace($url_http, $url_https, $str_output);
}

/**
 * Adds "External Header Footer" under the Settings menu, points the entry to be run by external_header_footer_do_settings_page().
 *
 * @return void
 */
function external_header_footer_settings_page() {
	add_options_page( 'External Header Footer Settings', 'External Header Footer', 'manage_options', 'external_header_footer_settings', 'external_header_footer_do_settings_page' );
}
add_action( 'admin_menu', 'external_header_footer_settings_page' );

/**
 * Outputs the overall External Header Footer settings page (the output of its fields get their own function, this contains the nonce and other stuff).
 *
 * @return void
 */
function external_header_footer_do_settings_page() {
	if ( !current_user_can( 'manage_options' ) )  {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
	?>
    <div class="wrap">
    	<div id="icon-options-general" class="icon32"><br /></div>
    	<h2>External Header Footer Settings</h2>

		<form method="post" action="options.php">
			<table class="form-table">
				<tbody>
					<?php do_settings_sections('external_header_footer_settings_page'); ?>
				</tbody>
			</table>

			<p class="submit">
				<input name="Submit" type="submit" class="button-primary" value="<?php esc_attr_e( 'Save Changes' ); ?>"/>
			</p>

			<?php settings_fields('external_header_footer_settings_group'); ?>	
		</form>
    </div>
    <?php
}

/**
 * Outputs the overall External Header Footer settings page (the output of its fields get their own function, this contains the nonce and other stuff).
 *
 * @return void
 */
function external_header_footer_init() {
    // Add the settings section that all of our fields will belong to (heading not shown).
    add_settings_section('external_header_footer_settings_section', '', 'ehf_header_footer_settings_section_text', 'external_header_footer_settings_page');

    // Add the "Expose Header and Footer" field (blank title here, output in its function), registered to the group "external_header_footer_settings_group", and 
    // output in the function ehf_expose_header_and_footer_checkbox().
    add_settings_field('ehf_expose_header_and_footer', '', 'ehf_expose_header_and_footer_checkbox', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
    register_setting('external_header_footer_settings_group', 'ehf_expose_header_and_footer', 'ehf_flush_rewrite_rules');

    // Add the "Force Use Of Absolute URLs" field (blank title here, output in its function), registered to the group "external_header_footer_settings_group", and 
    // output in the function ehf_expose_force_use_of_absolute_urls_checkbox().
    add_settings_field('ehf_force_use_of_absolute', '', 'ehf_expose_force_use_of_absolute_urls_checkbox', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
    register_setting('external_header_footer_settings_group', 'ehf_force_use_of_absolute');

    // Add the "Force Use Of HTTPS" field (blank title here, output in its function), registered to the group "external_header_footer_settings_group", and 
    // output in the function ehf_expose_force_use_of_https_checkbox().
    add_settings_field('ehf_force_use_of_https', '', 'ehf_expose_force_use_of_https_checkbox', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
    register_setting('external_header_footer_settings_group', 'ehf_force_use_of_https');

	// Add Current Page Item
	add_settings_field('ehf_external_current_page', '', 'ehf_external_current_page_item', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
	register_setting('external_header_footer_settings_group', 'ehf_external_current_page');

    // Add the "External Header URL" field (blank title here, output in its function), registered to the group "external_header_footer_settings_group", output in 
    // the function ext_external_header_url_text() and using the sanitizing function of ehf_external_clear_cache().
	add_settings_field('ehf_external_header_url', '', 'ext_external_header_url_text', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
    register_setting('external_header_footer_settings_group', 'ehf_external_header_url', 'ehf_external_clear_cache');

    // Add the "External Footer URL" field (blank title here, output in its function), registered to the group "external_header_footer_settings_group", output in 
    // the function ext_external_footer_url_text() and using the sanitizing function of ehf_external_clear_cache().
	add_settings_field('ehf_external_footer_url', '', 'ext_external_footer_url_text', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
    register_setting('external_header_footer_settings_group', 'ehf_external_footer_url', 'ehf_external_clear_cache');

    // Add the "Cache External Header/Footer Expiry" field (blank title here, output in its function), registered to the group "external_header_footer_settings_group", and
    // output in the function ext_external_footer_url_text().
	add_settings_field('ehf_external_cache_expiry', '', 'ehf_external_cache_expiry_text', 'external_header_footer_settings_page', 'external_header_footer_settings_section');
    register_setting('external_header_footer_settings_group', 'ehf_external_cache_expiry');
}
add_action('admin_init', 'external_header_footer_init');

function ehf_header_footer_settings_section_text() {
	1;
}

function ehf_expose_header_and_footer_checkbox() {
	// Retrieve and expose the "Expose Header and Footer" setting.
	$ehf_expose_header_and_footer = (int) get_option('ehf_expose_header_and_footer', 0);
	$ehf_expose_header_and_footer_checked = '';
	if ( $ehf_expose_header_and_footer == 1 ) {
		$ehf_expose_header_and_footer_checked = ' checked="checked"';
	}

	// Retrieve the URLs for the header, footer and test page.
	$ehf_header_url = home_url('/external-header-footer/header/');
	$ehf_footer_url = home_url('/external-header-footer/footer/');
	$ehf_test_url = plugins_url('external-header-footer/test-page.php');
	?>
	<tr valign="top">
		<th colspan="2">
			<h3>Expose Header for External Sites</h3>
			<p style="font-weight: normal;">
				If you're got an external website that you'd like to dress up with the same header and footer as this WordPress site, check the <b>Expose Header and Footer</b> 
				option below, and run a script on that external site to pull down the contents of the <b>Header URL</b> and <b>Footer URL</b> on a regular basis to keep the 
				two site looking the same.
			</p>
			<p style="font-weight: normal;">
				Once you've checked <b>Expose Header and Footer</b> and pressed the <b>Save Changes</b> button to enable the option, check out the page at <b>Demo Page URL</b>, 
				and take a look at its source code to see an example of how to retrieve and display the header and footer of this website using PHP.
			</p>
		</th>
	</tr>

	<tr valign="top">
		<th scope="row">Expose Header and Footer</th>
		<td> 
			<legend class="screen-reader-text"><span>Expose Header and Footer</span></legend>
			<label for="ehf_expose_header_and_footer">
				<input name="ehf_expose_header_and_footer" type="checkbox" id="ehf_expose_header_and_footer" value="1" <?php echo $ehf_expose_header_and_footer_checked; ?>/> 
				Allow this site's header and footer can be consumed by other websites
			</label>
		</td>
	</tr>

	<tr valign="top">
		<th scope="row">
			<label for="ext_header_url">Header URL</label>
		</th>
		<td>
			<code><a target="_blank" href="<?php echo $ehf_header_url; ?>"><?php echo $ehf_header_url; ?></a></code>
			<p class="description">Provide this URL to those looking to display this site's header on another website. (Remember, you can modify the output of the URL above through use of the <code>external_header_footer_pre_header()</code> action.)
		</td>
	</tr>

	<tr valign="top">
		<th scope="row">
			<label for="ext_footer_url">Footer URL</label>
		</th>
		<td>
			<code><a target="_blank" href="<?php echo $ehf_footer_url; ?>"><?php echo $ehf_footer_url; ?></a></code>
			<p class="description">Provide this URL to those looking to display this site's footer on another website. (Remember, you can modify the output of the URL above through use of the <code>external_header_footer_pre_footer()</code> action.)
		</td>
	</tr>

	<tr valign="top">
		<th scope="row">
			<label for="ext_footer_url">Demo Page URL</label>
		</th>
		<td>
			<code><a target="_blank" href="<?php echo $ehf_test_url; ?>"><?php echo $ehf_test_url; ?></a></code>
			<p class="description">This page acts as a demonstration of what a page on this website would look like wrapped with an external site's header and footer.</p>
		</td>
	</tr>
	<?php
}

function ehf_expose_force_use_of_https_checkbox() {
	// Retrieve and expose the "Force Use Of HTTPS" setting.
	$ehf_force_use_of_https = (int) get_option('ehf_force_use_of_https', 0);
	$ehf_force_use_of_https_checked = '';
	if ( $ehf_force_use_of_https == 1 ) {
		$ehf_force_use_of_https_checked = ' checked="checked"';
	}
	?>
	<tr valign="top">
		<th scope="row">Force Use Of HTTPS</th>
		<td> 
			<legend class="screen-reader-text"><span>Force Use Of HTTPS</span></legend>
			<label for="ehf_force_use_of_https">
				<input name="ehf_force_use_of_https" type="checkbox" id="ehf_force_use_of_https" value="1" <?php echo $ehf_force_use_of_https_checked; ?>/> 
				Force all URLs pointing to your this WordPress site's domain in your header and footer to be automatically rewritten to use HTTPS
			</label>
		</td>
	</tr>
	<?php
}

function ehf_expose_force_use_of_absolute_urls_checkbox() {
	// Retrieve and expose the "Force Use Of Absolute URLs" setting.
	$ehf_force_use_of_absolute = (int) get_option('ehf_force_use_of_absolute', 0);
	$ehf_force_use_of_absolute_checked = '';
	if ( $ehf_force_use_of_absolute == 1 ) {
		$ehf_force_use_of_absolute_checked = ' checked="checked"';
	}
	?>
	<tr valign="top">
		<th scope="row">Force Use Of Absolute URLs</th>
		<td> 
			<legend class="screen-reader-text"><span>Force Use Of Absolute URLs</span></legend>
			<label for="ehf_force_use_of_absolute">
				<input name="ehf_force_use_of_absolute" type="checkbox" id="ehf_force_use_of_absolute" value="1" <?php echo $ehf_force_use_of_absolute_checked; ?>/> 
				Convert all URLs that are relative to the site root to absolute URLs
			</label>
		</td>
	</tr>
	<?php
}

function ehf_consume_header_and_footer_checkbox() {
	// Retrieve and expose the "Consume Header and Footer" setting.
	$ehf_consume_header_and_footer = (int) get_option('ehf_consume_header_and_footer', 0);
	$ehf_consume_header_and_footer_checked = '';
	if ( $ehf_consume_header_and_footer == 1 ) {
		$ehf_consume_header_and_footer_checked = ' checked="checked"';
	}
	?>
	<tr valign="top">
		<th scope="row">Consume External Header / Footer</th>
		<td> 
			<legend class="screen-reader-text"><span>Consume Header and Footer</span></legend>
			<label for="ehf_consume_header_and_footer">
				<input name="ehf_consume_header_and_footer" type="checkbox" id="ehf_consume_header_and_footer" value="1" <?php echo $ehf_consume_header_and_footer_checked; ?>/> 
				If checked, the <code>ehf_output_external_header()</code> and <code>ehf_output_external_footer()</code> functions will output the contents of the header and footer URLs listed below
			</label>
		</td>
	</tr>
	<?php
}

function ext_external_header_url_text() {
	// Retrieve the URL for the external header.
	$ehf_external_header_url = get_option('ehf_external_header_url', '');
	?>
	<tr valign="top">
		<th colspan="2">
			<h3>Consume Header / Footer from External Website</h3>
			<p style="font-weight: normal;">
				If you've enabled the External Header Footer plug-in on another WordPress website, and want to use its header on <i>this</i> WordPress website, you can use the 
				fields below to automatically retrieve the header and footer of that website. 
			</p>
			<p style="font-weight: normal;">
				Next, update the <code>header.php</code> and <code>footer.php</code> files of this WordPress theme to call the function <code>ehf_output_external_header()</code> 
				and <code>ehf_output_external_footer()</code> respectively. This plug-in will automatically retrieve and cache the contents of the external site's header and 
				footer for the amount of minutes specified in <b>Cache Header/Footer For</b>.
		</th>
	</tr>

	<tr valign="top">
		<th scope="row"><label for="ehf_external_header_url">External Header URL</label></th>
		<td>
			<input name="ehf_external_header_url" type="text" id="ehf_external_header_url" value="<?php echo $ehf_external_header_url; ?>" class="regular-text code" style="width: 600px;" />
			<p class="description">The function <code>ehf_output_external_header()</code> will output the contents of the page retrieved at the URL input into the field above.</p>
		</td>
	</tr>
	<?php
}

function ext_external_footer_url_text() {
	// Retrieve the URL for the external footer.
	$ehf_external_footer_url = get_option('ehf_external_footer_url', '');
	?>
	<tr valign="top">
		<th scope="row"><label for="ehf_external_footer_url">External Footer URL</label></th>
		<td>
			<input name="ehf_external_footer_url" type="text" id="ehf_external_footer_url" value="<?php echo $ehf_external_footer_url; ?>" class="regular-text code" style="width: 600px;" />
			<p class="description">The function <code>ehf_output_external_footer()</code> will output the contents of the page retrieved at the URL input into the field above.</p>
		</td>
	</tr>		
	<?php
}

function ehf_external_cache_expiry_text() {
	// Retrieve the cache expiry time limit (in minutes).
	$ehf_external_cache_expiry = get_option('ehf_external_cache_expiry', '60');

	// Retrieve the URLs for the external test page.
	$ehf_external_test_url = home_url('/external-header-footer/demo/');
	?>
	<tr valign="top">
		<th scope="row"><label for="ehf_external_cache_expiry">Cache Header/Footer For</label></th>
		<td>
			<input name="ehf_external_cache_expiry" type="text" id="ehf_external_cache_expiry" value="<?php echo $ehf_external_cache_expiry; ?>" class="regular-text" style="width: 75px;" /> minutes
			<p class="description">The amount of time that the external header/footer should be cached locally for before being retrieved again.</p>
		</td>
	</tr>	

	<tr valign="top">
		<th scope="row">
			<label for="ext_footer_url">External Demo Page URL</label>
		</th>
		<td>
			<code><a target="_blank" href="<?php echo $ehf_external_test_url; ?>"><?php echo $ehf_external_test_url; ?></a></code>
			<p class="description">This page demonstrates what an external page wrapped with the specified external header and footer would look like.</p>
		</td>
	</tr>	
	<?php	
}

function ehf_external_current_page_item() {
	// Retrieve current page item
	$ehf_external_current_page = get_option('ehf_external_current_page');
	$menuLocations = get_nav_menu_locations(); // Get our nav locations (set in our theme, usually functions.php)
	// This returns an array of menu locations ([LOCATION_NAME] = MENU_ID);

	$menuID = $menuLocations['primary'];
	$primaryNav =  wp_get_nav_menu_items($menuID);

	?>
    <tr valign="top">
        <th scope="row"><label for="ehf_current_page">Current Page Item</label></th>
        <td>
            <select name="ehf_external_current_page" id="ehf_external_current_page">
                <option value="">choose Menu</option>
				<?php echo '1111';
				foreach ($primaryNav AS $item){
					if($item->menu_item_parent == 0) {
						echo '<option value="' . $item->ID . '" ' . ($item->ID == $ehf_external_current_page ? 'selected' : '') . '>' . $item->title . '</option>';
					}
				}
				?>
            </select>


            <p class="description">The Menu that should be highlighted as "active".</p>
        </td>
    </tr>
	<?php
}



/**
 * Called when a new value is sent to the "Expose Header and Footer" field; flushes the internal cache of WordPress rewrite rules / permalinks 
 * to ensure the new rules for the plug-in are accessible.
 *
 * @return string
 */
function ehf_external_clear_cache( $value ) {
	delete_transient('ehf_external_header_url');
	delete_transient('ehf_external_footer_url');

	return $value;
}

/**
 * Called when a new value is sent to the "External Header URL" or "External Footer URL"; clears the Transients API cache of 
 * what may already be saved to those fields to ensure changes to what is wished to be retrieved occurs immediately.
 *
 * @return integer
 */
function ehf_flush_rewrite_rules( $value ) {
	global $wp_rewrite;

	$wp_rewrite->flush_rules( false );

	return $value;
}
?>