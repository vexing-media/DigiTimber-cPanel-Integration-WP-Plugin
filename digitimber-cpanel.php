
<?php
/*
Plugin Name: DigiTimber cPanel Integration
Plugin URI: https://github.com/vexing-media/DigiTimber-cPanel-Integration-WP-Plugin
Description: Access basic cPanel functions (currently limited to email) from within WordPress. This allows your customers to use the interface that they already know and love to perform basic admin tasks.
Version: 1.4.5
Author: DigiTimber
Author URI: https://www.digitimber.com/
License: GPL2
DigiTimber cPanel Integration is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.
DigiTimber cPanel Integration is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with DigiTimber cPanel Integration. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/

require_once("dtcpaneluapi.class.php");

/* =======================================================================================================================
   ================================================= WP Functions ========================================================
   ======================================================================================================================= */

add_action( 'admin_menu', 'digitimber_cpanel_menu' );
register_activation_hook( __FILE__, 'dt_cpanel_activate' );
register_uninstall_hook( __FILE__, 'dt_cpanel_uninstall');

// WP Utility function called when activating the plugin
function dt_cpanel_activate(){
	//need to check and see if keys exist, if they do, don't regenerate them (delete and re-install the plugin to regenerate)
	if(!get_option("dt_cpanel_key")){
		// Register the options we are going to use in the plugin
		register_setting( 'dt_cpanel_key', 'dt_cpanel_key' ); 
		register_setting( 'dt_cpanel_settings', 'dt_cpanel_settings' ); 
		register_setting( 'dt_cpanel_domains', 'dt_cpanel_domains' ); 
		// On first install, generate keys for use in encrypting the cPanel credentials and store them in the database
		$k1 = base64_encode(openssl_random_pseudo_bytes(32));
		$k2 = base64_encode(openssl_random_pseudo_bytes(64));
		add_option( 'dt_cpanel_key', array("key1"=>$k1,"key2"=>$k2), '', 'yes' );
	}
}

// WP Utility function called when deleting the plugin
function dt_cpanel_uninstall(){
	//remove the old version settings (v1.3.3 and below)
	delete_option("cpanel_key");
	delete_option("cpanel_settings");
	delete_option("cpanel_domains");

	//remove the settings (v1.4.3 and above)
	delete_option("dt_cpanel_key");
	delete_option("dt_cpanel_settings");
	delete_option("dt_cpanel_domains");
}

// WP Utility function to setup menus 
function digitimber_cpanel_menu() {
	// Add Toplevel
	add_menu_page(__('cPanel Integration','digitimber-cpanel'), __('cPanel Integration','digitimber-cpanel'), 'administrator', 'dt-top-level-handle', 'dt_cpanel_main_page', 'dashicons-admin-tools' );

	// Add a submenu for Email
	add_submenu_page('dt-top-level-handle', __('Email Administration','digitimber-cpanel-email'), __('Email Administration','digitimber-cpanel-email'), 'administrator', 'dt-cpanel-email', 'dt_cpanel_email');

	// Add a submenu for Settings (Also create a Settings -> cPanel Settings section)
	add_submenu_page('dt-top-level-handle', __('Settings','dt-cpanel-settings-page'), __('Settings','dt-cpanel-settings-page'), 'administrator', 'dt-cpanel-settings-page', 'dt_cpanel_settings_page');
	add_options_page(__('cPanel Settings','digitimber-cpanel'), __('cPanel Settings','digitimber-cpanel'), 'administrator', 'dt-cpanel-settings-page', 'dt_cpanel_settings_page');
} 


/* =======================================================================================================================
   ================================================ Plugin Functions =====================================================
   ======================================================================================================================= */

// Utility function used to provide an array of all possible domain names associated to the account including main, addon, alias, and sub
function dt_cpanel_getDomainList() { 

	$options = get_option( 'dt_cpanel_settings' ); //grab the cPanel username and password from the database

	// Create a new connection to the cPanel server and verify it's operational with a quick test of StatsBar->get_stats
	$cPanel = new DTcPanelAPI(dt_cpanel_crypt($options['cpun']), urldecode(dt_cpanel_crypt($options['cppw'])), '127.0.0.1');
	$response = $cPanel->StatsBar->get_stats(); // Generic call used to verify we are logged in successfully
	if (isset($response->errors[0]) && $response->errors[0] != ''){
		dt_cpanel_error_notice($response->errors[0]);
		return;
	} elseif ($response == NULL) { // if the response is NULL there is a high likelyhood that it could not connect, most likely caused by username and password issues
                dt_cpanel_error_notice("The login information provided does not appear to be correct. Please check your username and password and try again.",0);
		return;
	}

	// Gather all the domains on the account
	$response = $cPanel->DomainInfo->list_domains();

	// Collect the response data for each section and assign it to a variable for easier consumption later
        $domain_data[0] = $response->data->main_domain;
	$alias = $response->data->parked_domains;
	$addon = $response->data->addon_domains;
	$sub = $response->data->sub_domains;

	$cPanel = null; // Cleanup connection

	// If an array has anything in it, append it to the existing domain_data array
	if (is_array ($alias) && sizeof($alias) > 0) { 
		$domain_data = array_merge($domain_data, $alias);
	}
	if (is_array($addon) && sizeof($addon) > 0) { 
		$domain_data = array_merge($domain_data, $addon);
	}
	if (is_array($sub) && sizeof($sub) > 0) { 
		$domain_data = array_merge($domain_data, $sub);
	}

	sort($domain_data); // Aphabetize the entire list since each section will be ordered on its own
	return $domain_data; // Return array of domains for whatever called it
}


// Utility function for displaying error messages. No HTML allowed at this point
function dt_cpanel_error_notice($err_string) {
	printf( '<div class="notice notice-error"><p>%1$s</p></div><BR><BR>', esc_html($err_string));
}

// Utility function to encrypt and decryption cPanel credentials using OpenSSL
function dt_cpanel_crypt($string,$action = false) {
        settings_fields( 'dt_cpanel_key' );
        do_settings_sections( __FILE__ );
	$key = get_option( 'dt_cpanel_key' );
	$cipher = "aes-256-cbc";
	if (in_array($cipher, openssl_get_cipher_methods())) {
		$iv = substr( hash( 'sha256', $key['key1'] ), 0, 16 );
		if ($action) {
			$output = openssl_encrypt($string, $cipher, $key['key2'], $options=0, $iv);
		} else {
			$output = openssl_decrypt($string, $cipher, $key['key2'], $options=0, $iv);
		}
	} else {
	        add_settings_error('dt_cpanel_settings', 'cpanel_invalid_entry', 'Invalid cipher! Please check that your host supports openssl using aes-256-cbc.', $type = 'error');
	}
	return $output;
}

// Utility function to create a table on the fly using key/value pairs in an array
function dt_cpanel_build_table($array){

    // Start the table HTML code
    $html = '<table>';

    // Generate Table Headers
    $html .= '<tr>';
    foreach($array[0] as $key=>$value){
            $html .= '<th>' . htmlspecialchars($key) . '</th>';
        }
    $html .= '</tr>';

    // Generate Data Rows
    foreach( $array as $key=>$value){
        $html .= '<tr>';
        foreach($value as $key2=>$value2){
            $html .= '<td>' . htmlspecialchars($value2) . '</td>';
        }
        $html .= '</tr>';
    }

    // Close out the table HTML code and return the html 
    $html .= '</table>';
    return $html;
}


// Utility function used to provide stats about the cPanel server
function dt_cpanel_getCpanelInfo() { 

	$options = get_option( 'dt_cpanel_settings' ); //grab the cPanel username and password from the database

	// Create a new connection to the cPanel server and verify it's operational with a quick test of StatsBar->get_stats
	$cPanel = new DTcPanelAPI(dt_cpanel_crypt($options['cpun']), urldecode(dt_cpanel_crypt($options['cppw'])), '127.0.0.1');
	$response = $cPanel->StatsBar->get_stats(); // Generic call used to verify we are logged in successfully
	if (isset($response->errors[0]) && $response->errors[0] != ''){
		dt_cpanel_error_notice($response->errors[0]);
		return;
	} elseif ($response == NULL) { // if the response is NULL there is a high likelyhood that it could not connect, most likely caused by username and password issues
                dt_cpanel_error_notice("The login information provided does not appear to be correct. Please check your username and password and try again.",0);
		return;
	}
	
	echo "<h2>cPanel Usage Statistics</h2><BR>";
	$email = array(
		array('Email Accounts'=>$cPanel->Email->count_pops()->data, // Range starts at 0, but we also have the primay account as an email, which we are excluding
		'Email Forwarders'=>$cPanel->Email->count_forwarders()->data+1, // Range starts at 0, so we have to add one to compensate in real world counting
		'Current Disk Usage'=>$cPanel->StatsBar->get_stats(['display'=> "diskusage"])->data[0]->count)
		);
	echo dt_cpanel_build_table($email);
	echo "<BR><HR><BR>";

}



/* =======================================================================================================================
   ===================================================== Pages ===========================================================
   ======================================================================================================================= */



// ==================== Main Page ==========================
function dt_cpanel_main_page() {
    $html = "<style>
		table { border-collapse: collapse; }
		tr { border: none; }
		td {
			text-align: center;
			border-right: solid 1px #000; 
			border-left: solid 1px #000;
		}
		th {
			text-align: center;
			padding: 5px;
			border-right: solid 1px #000; 
			border-left: solid 1px #000;
		}
		</style>";
	$html .= "<h2>" . __( 'DigiTimber cPanel Integration Plugin', 'digitimber-cpanel' ) . '</h2>
		<a href="https://github.com/vexing-media/DigiTimber-cPanel-Integration-WP-Plugin" target="_blank">GitHub Repo</a><BR>
		<a href="https://www.digitimber.com/" target="_blank">Company Website</a><BR><BR><a href="https://www.digitimber.com/get-website-hosting/?promocode=wpplugin25" target="_blank">Get WordPress hosting for 25% off today!</a>
		 ';
	echo $html;
	dt_cpanel_getCpanelInfo();
}



// ==================== Email Administration Page ==========================
function dt_cpanel_email() {
	$options = get_option( 'dt_cpanel_settings' );
	$dt_cpanel_domains = get_option( 'dt_cpanel_domains' );
	$show_array = $dt_cpanel_domains['show_array'];
	$cPanel = new DTcPanelAPI(dt_cpanel_crypt($options['cpun']), urldecode(dt_cpanel_crypt($options['cppw'])), '127.0.0.1');

	// New style elements, need to move to css at some point
	echo "<style>
		tr.border_bottom td {  border-bottom:1pt solid black; }
		hr.dark {  border-top:1pt solid black; max-width: 500px; margin-left: 0px;}
		tr.border_bottom_lt td {  border-bottom:1pt solid #ccc; }
	    </style>";
    
	if (isset($_POST['email']) && $_POST['email'] != '')  {
		$_POST['email'] = sanitize_email($_POST['email']);
		echo "<h1>" . __( 'Email Administration ('.$_POST['email'].')', 'dt-cpanel-email' ) . "</h1>";
	} else {
		echo "<h1>" . __( 'Email Administration', 'dt-cpanel-email' ) . "</h1>";
	}
    
	// Delete Operation Submitted
	if (isset($_POST['delete']) && $_POST['delete'] == 1) {
		if ( !isset($_POST['delete_nonce']) || !wp_verify_nonce($_POST['delete_nonce'], 'dt-cpanel-email-delete')) {
			dt_cpanel_error_notice("Sorry, we can't seem to validate that this request was submitted correctly. Please check your settings and try again.");
			return;
		}
		$_POST['delemail'] = sanitize_email($_POST['delemail']);
		list($user, $domain) = explode('@', $_POST['delemail']);
        	echo "<BR><B>Attempting to delete email for $user@$domain, please wait...</b><BR>";
		$response = $cPanel->Email->delete_pop([
			'email'	=> "$user",
			'domain' => "$domain"
		]);
/*		if ($del_forwarder) { // working on adding a forwarders management page in the future
	        	echo "<BR><B>Attempting to delete email for $user@$domain, please wait...</b><BR>";
			$response = $cPanel->Email->delete_forwarder([
				'address'	=> "$address",
				'forwarder' => "$forwarder"
			]);
		}
*/
			
		if (isset($response->errors[0]) && $response->errors[0] != ''){
			dt_cpanel_error_notice($response->errors[0]);
			return;
		}
		echo("<meta http-equiv='refresh' content='0'>");
		return;
        }

	// Create Operation Submitted
	if (isset($_POST['create']) && $_POST['create'] == 1) {
		if ( !isset($_POST['create_nonce']) || !wp_verify_nonce($_POST['create_nonce'], 'dt-cpanel-email-create')) {
			dt_cpanel_error_notice("Sorry, we can't seem to validate that this request was submitted correctly. Please check your settings and try again.");
			return;
		}
		if (isset($_POST['password']) && $_POST['password'] != '') {
			$pass = $_POST['password'];
		} else {
			dt_cpanel_error_notice("Password cannot be blank when creating an account. Please try again.");
			return;
		}
		
		$user = sanitize_user($_POST['user']);
		$domain = sanitize_user($_POST['domain']);
		if (isset($_POST['max']) && $_POST['max'] == 1)
			$quota = 0;
		else 
			$quota = round($_POST['quota'],0);

		echo "<B>Attempting to create email for $user@$domain, please wait...</a><BR>";
		$response = $cPanel->Email->add_pop([
			'domain' => "$domain",
			'password' => "$pass",
			'quota' => "$quota",
			'send_welcome_email' => '1',
			'email' =>"$user"
		]);
		if (isset($response->errors[0]) && $response->errors[0] != ''){
			dt_cpanel_error_notice($response->errors[0]);
			return;
		}
		echo("<meta http-equiv='refresh' content='0'>");
		return;
	}

	// Manage Operation Submitted
	if (isset($_POST['manage']) && $_POST['manage'] == 1) {
		$disabled = '';
		$checked = '';
		list($user, $domain) = explode('@', $_POST['email']);
		if (isset($_POST['max']) && $_POST['max'] == 1)
			$quota = 0;
		else {
			if ($_POST['quota'] == "None") { $quota = 0; $disabled = "disabled"; $checked = "checked";} else {
				//Replace the &nbsp with ' ' so we can properly explode and remove any commas from the value
				list($quota_num, $postfix) = explode(' ', str_replace(',', '', str_replace("\xc2\xa0", ' ', $_POST['quota'])));
				switch($postfix) {
					case 'MB': $quota = round($quota_num,0); break;
					case 'GB': $quota = round($quota_num * 1024, 0); break;
					case 'TB': $quota = round($quota_num * 1024 * 1024, 0); break;
					default: dt_cpanel_error_notice("Unable to convert quota to MB ($quota_num $postfix). Please submit this error with a bug report."); break;
				}
			}
		}
		echo "<BR><table width=50%>";
		echo "<tr class=border_bottom><td width=200px><B>Email Account</b></td><td>New Password</td><td><b>Quota in MB</b></td><td></td><td></td></tr>";
	   	echo "<form method=post><tr><td valign=top>$user@$domain<input type=hidden name=email value=$user@$domain></td>"; 
		wp_nonce_field( 'dt-cpanel-email-update', 'update_nonce' );
		echo "<td><input name=password type=textbox><BR>(Leave blank to not change)</td>";
		echo "<td><input type=textbox id=quota name=quota value=$quota $disabled><BR><input onchange=\"document.getElementById('quota').disabled = this.checked;\" type=checkbox $checked name=max value=1> Unlimited Storage</td><td valign=top><input type=hidden value=1 name=update><input type=submit value=Update></td></form>";
		echo "<form method=post onsubmit=\"return confirm('Do you really want to delete $user@$domain?');\">";
		wp_nonce_field( 'dt-cpanel-email-delete', 'delete_nonce' );
		echo "<td valign=top><input type=hidden name=delete value=1><input type=hidden name=delemail value=$user@$domain><input type=submit value=Delete></td></form></tr>";
		echo "</table><BR><a href=\"?page=dt-cpanel-email\">Back</a>";
		return;
	}

	// Update Operation Submitted
	if (isset($_POST['update']) && $_POST['update'] == 1) {
		if ( !isset($_POST['update_nonce']) || !wp_verify_nonce($_POST['update_nonce'], 'dt-cpanel-email-update')) {
			dt_cpanel_error_notice("Sorry, we can't seem to validate that this request was submitted correctly. Please check your settings and try again.");
			return;
		}
		if (isset($_POST['max']) && $_POST['max'] == 1) {
			$quota = 0;
		} else {
			$quota = round($_POST['quota'],0);
		}
		list($user, $domain) = explode('@', $_POST['email']);
		echo "<B>Attempting to update $user@$domain, please wait...</a><BR>";
		if (isset($_POST['password']) && $_POST['password'] != '') {
			$passwd = $_POST['password'];
			$response = $cPanel->Email->passwd_pop([
			        'email'           => "$user",
	        		'password'        => "$passwd",
		        	'domain'          => "$domain"
			]);
			if (isset($response->errors[0]) && $response->errors[0] != ''){
				dt_cpanel_error_notice($response->errors[0]);
				return;
			}
		}
		$response = $cPanel->Email->edit_pop_quota([
		        'email'           => "$user",
	        	'quota'           => "$quota",
		        'domain'          => "$domain"
		]);
		if (isset($response->errors[0]) && $response->errors[0] != ''){
			dt_cpanel_error_notice($response->errors[0]);
			return;
		}
		echo("<meta http-equiv='refresh' content='0'>");
		return;
	}


	// Default Page Display Output
	$response = $cPanel->Email->list_pops_with_disk();
	if (isset($response->errors[0]) && $response->errors[0] != ''){
		dt_cpanel_error_notice($response->errors[0]);
		return;
	}
	if (sizeof($show_array) <= 0) {
		dt_cpanel_error_notice("No domains to display, please verify that the username and password is correct and that you have at least one domain selected.");
		return;
	} 
	echo "<BR></GR><h2>Current Email Accounts:</h2><table width=50%>";
	if (sizeof($response->data) > 0) {
	        echo "<tr class=border_bottom><td width=200px><B>Email Account</b><td><b>Disk Used</b></td><td><b>Disk Quota</b></td><td></td></tr>";
		// Init Counter for loop
		$c=0;
		$odata = array();
		foreach ($response->data as $data) {
			if (filter_var($data->login, FILTER_VALIDATE_EMAIL)) {
				list($junk, $cur_domain) =  explode('@', $data->login);
				if (in_array($cur_domain, $show_array)) {
	        	        	$odata[$c][0] = $data->login;
					$odata[$c][1] = $data->humandiskused;
					$odata[$c][2] = $data->humandiskquota;
					$c++;
				}
			}
		}
		// Alphabetize our list of email addresses for ease of finding them
		// ToDo: Add pages for lists and a default setting for number of elements listed
		if (sizeof($odata)>0) {
			sort($odata);
			for ($i=0;$i<sizeof($odata);$i++) {
				echo "<tr class=border_bottom_lt><td style='white-space:nowrap'>".$odata[$i][0]."</td><td>".$odata[$i][1]."</td><td>".$odata[$i][2]."</td>";
				echo "<form method=post><td align=right><input type=hidden name=manage value=1><input type=hidden name=email value=".$odata[$i][0]."><input type=hidden name=quota value=".$odata[$i][2]."><input type=submit value='Manage Email Account'></td></form></tr>";
			}
			echo "</table><BR>$i of $i Emails Displayed.";
		} else {
			echo "</table><BR>No Emails to Display.";
		}

	} else {
		echo "</table><BR>No Emails to Display.";
	}


	// Create new Email Form
	$domain_list = dt_cpanel_getDomainList();
   	echo "<form method=post><BR><HR class=dark><h2><b>Create New Email Address:</h2></b><table>";
	echo "<tr><td>Email Address:</td><td><input autocomplete=lolwhut type=textbox name=user>@<select name=domain>";
		foreach($domain_list as $dom) {
			if (in_array($dom, $show_array)) {
				echo "<option value=$dom>$dom</option>";
			}
		}
	echo "</select></td></tr><tr><td>Email Password:</td><td><input name=password type=textbox></td></tr>";
	wp_nonce_field( 'dt-cpanel-email-create', 'create_nonce' );
	echo "<tr><td valign=top>Email Quota (in MB):</td><td><input type=textbox id=quota name=quota value=2048><BR><input onchange=\"document.getElementById('quota').disabled = this.checked;\" type=checkbox name=max value=1> Unlimited Storage</td></tr></table><input type=hidden value=1 name=create><input type=submit value=Create>
	</form>";
}


// ==================== Settings Page ==========================
function dt_cpanel_settings_page() {

	$options = get_option( 'dt_cpanel_settings' );
	$dt_cpanel_domains = get_option( 'dt_cpanel_domains' );

	echo "<h2>" . __( 'Settings Page', 'dt-cpanel-settings-page' ) . "</h2><BR>";

	// If we are making an update to the settings page, display success or failure
	if (isset($_POST['settings_update']) && $_POST['settings_update'] == 1) {
		if ( !isset($_POST['settings_update_nonce']) || !wp_verify_nonce($_POST['settings_update_nonce'], 'settings_update_nonce')) {
			dt_cpanel_error_notice("Sorry, we can't seem to validate that this request was submitted correctly. Please check your settings and try again.",1);
		}
		echo "<B>Updating settings, please wait...</b><BR>";
		$show_array = array(); // Init Array we are going to push to
		if (isset($_POST['show_array']) && $_POST['show_array'] != '') {
			foreach($_POST['show_array'] as $domain) {
				array_push($show_array, $domain); // Grab all the arrays that were enabled and drop them into an array for storage
			}
		}
		update_option( 'dt_cpanel_settings', array('cpun'=>dt_cpanel_crypt(sanitize_user($_POST['cpun']),1),
						   'cppw'=>dt_cpanel_crypt(urlencode($_POST['cppw']),1)), '', 'yes' );

		if (sizeof($show_array) <= 0) { // If the list is blank, probably our first run (or the user has unselected everything... 
			$domain_list = dt_cpanel_getDomainList(); // Generate a list of what we can pull and drop them into an array to save
			if (is_array($domain_list) && sizeof($domain_list) > 0) {
	        	        foreach($domain_list as $domain) {
					array_push($show_array, $domain); // Grab all the arrays that were enabled and drop them into an array for storage
				}
			}
		}
		update_option( 'dt_cpanel_domains', array('show_array'=>$show_array), '', 'yes' );
        	echo("<meta http-equiv='refresh' content='0'>"); // Force reload the page to pull the new data set.

	} else {

		// Loading the page without any submission, attempt to grab a domain list (will error if login is incorrect)
		$domain_list = dt_cpanel_getDomainList();
		if (isset($options['cpun']) && $options['cpun'] != '') $cpun_value = dt_cpanel_crypt($options['cpun']);
		if (isset($options['cppw']) && $options['cppw'] != '') $cppw_value = urldecode(dt_cpanel_crypt($options['cppw']));
		if (isset($dt_cpanel_domains)) 
			$show_array = $dt_cpanel_domains['show_array'];
		else
			$show_array = array();
		$domain_counter=0;
		echo "<form method=post autocomplete=off>";
		// if the option isn't set, or it's not an array, or if the list of domains pulled does not contain any data, then don't display anything
		if (isset($dt_cpanel_domains) && is_array($dt_cpanel_domains) && is_array($domain_list) && sizeof($domain_list) > 0) {
			echo "<h2>Select which domains should be accessible by this plugin (must be at least one):</h2><table>";
        	        foreach($domain_list as $domain) {
				if (in_array($domain, $show_array)) { $checked = "checked"; } else { $checked = ""; }
               	        	echo "<tr><td style='padding-left:15px'><input type=checkbox name=show_array[$domain_counter] value=$domain $checked >$domain</td></tr>";
				$domain_counter++;
       		        }
			echo "</table><BR>";
			if (sizeof($domain_list) > 6) { // If the domain list is larger than 6 elements, lets give the user a toggle button at the bottom to select/deselect all
				$html = '<script>
					var isAllCheck = false;
					function togglecheckboxes(cn) {
						isAllCheck = !isAllCheck;   
						var cbarray = document.querySelectorAll("input[type=\'checkbox\']");
						for(var i = 0; i < cbarray.length; i++){
					    		cbarray[i].checked = !isAllCheck
						}   
					}</script>
					<input type="Button" onclick="togglecheckboxes(\'cb\')" value="Toggle all" /><BR>';
			echo $html;
			}
		}
		$html = "<BR><hr><BR><h2>cPanel Login Information</h2><table><tr><td>cPanel Username:</td><td><input type='text' name='cpun' value='".$cpun_value."'></td></tr>
			<tr><td>cPanel Password:</td><td><input type='password' name='cppw' value='".$cppw_value."'></td></tr>
			</table><input type=hidden name=settings_update value=1>";
	        echo $html;
	        wp_nonce_field( 'settings_update_nonce', 'settings_update_nonce' );
        	submit_button();
		echo "</form>";
	}
}
