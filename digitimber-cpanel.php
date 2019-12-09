<?php
/*
Plugin Name: DigiTimber cPanel Integration
Plugin URI: https://github.com/vexing-media/DigiTimber-cPanel-Integration-WP-Plugin
Description: Access basic cPanel functions (currently limited to email) from within WordPress. This allows your customers to use the interface that they already know and love to perform basic admin tasks.
Version: 1.3.1
Author: DigiTimber
Author URI: http://www.digitimber.com/
License: GPL2
DigiTimber cPanel Integration is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 2 of the License, or any later version.
DigiTimber cPanel Integration is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with DigiTimber cPanel Integration. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/

require_once("dtcpaneluapi.class.php");

register_uninstall_hook(__FILE__, 'dt_cpanel_uninstallPlugin');

add_action( 'admin_menu', 'digitimber_cpanel_menu' );  
function digitimber_cpanel_menu() {
    // Add Toplevel
    add_menu_page(__('DigiTimber cPanel','digitimber-cpanel'), __('DigiTimber cPanel','digitimber-cpanel'), 'administrator', 'dt-top-level-handle', 'dt_cpanel_main_page', 'dashicons-admin-tools' );

    // Add a submenu for Email
    add_submenu_page('dt-top-level-handle', __('Email','digitimber-cpanel-email'), __('Email','digitimber-cpanel-email'), 'administrator', 'dt-cpanel-email', 'dt_cpanel_email');

    // Add a submenu for Settings (Also create a Settings -> cPanel Settings section)
    add_submenu_page('dt-top-level-handle', __('Settings','dt-cpanel-settings-page'), __('Settings','dt-cpanel-settings-page'), 'administrator', 'dt-cpanel-settings-page', 'dt_cpanel_settings_page');
    add_options_page(__('cPanel Settings','digitimber-cpanel'), __('cPanel Settings','digitimber-cpanel'), 'administrator', 'dt-cpanel-settings-page', 'dt_cpanel_settings_page');

} 

add_action( 'admin_init', 'dt_cpanel_register_settings' );
function dt_cpanel_register_settings() {   
	// Register the options we are going to use
        register_setting( 'cpanel_key', 'cpanel_key' ); 
        register_setting( 'cpanel_settings', 'cpanel_settings' ); 
}

add_action( 'admin_init', 'dt_cpanel_createRandomKeys' );
function dt_cpanel_createRandomKeys() {
	// On first install, generate keys for use in encrypting the cPanel credentials and store them in the database
	settings_fields( 'cpanel_key' );
	do_settings_sections( __FILE__ );
	$k1 = base64_encode(openssl_random_pseudo_bytes(32));
	$k2 = base64_encode(openssl_random_pseudo_bytes(64));
	add_option( 'cpanel_key', array("key1"=>$k1,"key2"=>$k2), '', 'yes' );
}

// Main page of the plugin - just data for now
function dt_cpanel_main_page() {
	echo "<h2>" . __( 'DigiTimber cPanel Integration', 'digitimber-cpanel' ) . "</h2><BR>";
	echo "Plugin Name: DigiTimber cPanel Integration Plugin<BR>"; ?>
	Plugin URI: <a href="https://github.com/vexing-media/DigiTimber-cPanel-Integration-WP-Plugin">https://github.com/vexing-media/DigiTimber-cPanel-Integration-WP-Plugin</a>
	<p><h2>== Description ==</h2></p>
	<p>DigiTimber cPanel Integration allows users to access basic cPanel functionality from within WordPress. This plugin was created initially for our own user, but decided that with the lack of any other plugins in the list, we'd toss it out there for others. Hopefully its helpful to you and your users!</p>
	<p>Currently limited to email administration, but more is planned.</p>
	<p><H2>== Frequently Asked Questions ==</p>
	<p><H3>= Is it secure to have my cPanel login credentials in my WordPress? =</p>
	<p>It's as secure as your wordpress site. We store the credentials using AES-256 encryption in the WordPress database. The salt and iv are computed once on installation so each installation is unique.</p>
	<p><H3>= Is there an undo option? =</p>
	<p>No. Unfortunately all changes made are immediately caried out on the server. Data loss may occur if you use the delete or modify options. Please ensure you have a valid backup of your data on cPanel while using any remote plugin.</p>
	<p><H3>= Where is all the documentation? =</p>
	<p>Currently there is no documentation besides this readme. More will become available as we add additional functionality.</p>
	<p><H3>= Do you make any other plugins? =</p>
	<p>Not at this time.</p>
	<p><H2>== Changelog ==</h2></p>
	<p><B>= 1.3.1 = 12/9/2019</b><br />- INFO: After submission to WP Plugin Directory, we had a few things to fix<br />- UPDATED: Changed the overall name of the plugin to DigiTimber cPanel Integration<br />- UPDATED: Including your own CURL code - Removed old curl library and wrote our own based on the WP HTTP api<br />- UPDATED: Generic function (and/or define) names - removed old function names that were not very specific and added (hopefully) appropriate naming<br />- UPDATED: Please sanitize, escape, and validate your POST calls - reviewed all input and applied applicable sanitation or encoding</p>
	<p><B>= 1.2.2 = 12/8/2019</b><br />- INFO: Initial Submission to WordPress Official Plugins List<br />- ADDED: Created this file, readme.txt<br />- ADDED: Addon Email management - lists Emails / add new email accounts / modify email accounts / delete email accounts<br />- UPDATED: Encrypt cPanel credentials for storage in the database using AES-256 with generated key and iv<br />- ADDED: New Github repo</p>
	<p><B>= 1.1.0 = 12/8/2019</b><br />- INFO: Added 3rd version identifier for security and patch updates. New format is Major.Minor.Patch<br />- UPDATED: Encrypt cPanel credentials for storage in the database using basic encryption and static key and iv</p>
	<p><B>= 1.0 = 12/1/2019</b><br />- ADDED: Email listings - ability to add and delete<br />- ADDED: First savings of settings in database, plain text<br />- INFO: First Release</p>
<?
}

function dt_cpanel_getDomainList() {
	// Used to provide an array of all possible domain names associated to the account including main, addon, alias, and sub
	$options = get_option( 'cpanel_settings' );
	$cPanel = new DTcPanelAPI(dt_cpanel_crypt($options['cpun']), urldecode(dt_cpanel_crypt($options['cppw'])), '127.0.0.1');
	$response = $cPanel->DomainInfo->list_domains();
	if (isset($response->errors[0]) && $response->errors[0] != ''){
		dt_cpanel_error_notice($response->errors[0],1);
	}

	// Collect domain data, primary domain is always first, and concantenate into a single array
	$domain_data[0] = $response->data->main_domain;
	$alias = $response->data->parked_domains;
	$addon = $response->data->addon_domains;
	$sub = $response->data->sub_domains;
	$c=1;	
	if (sizeof($alias) > 0) { sort($alias); foreach($alias as $domain) { $domain_data[$c] = $domain; $c++; } }
	if (sizeof($addon) > 0) { sort($addon); foreach($addon as $domain) { $domain_data[$c] = $domain; $c++; } }
	if (sizeof($sub) > 0) { sort($sub); foreach($sub as $domain) { $domain_data[$c] = $domain; $c++; } }

	return $domain_data; // Return array of domains
}

function dt_cpanel_settings_page() {
	$options = get_option( 'cpanel_settings' );
	echo "<h2>" . __( 'Settings Page', 'dt-cpanel-settings-page' ) . "</h2><BR>";
	if (isset($_POST['settings_update']) && $_POST['settings_update'] == 1) {
		echo "<B>Updating settings, please wait...</b><BR>";
		update_option( 'cpanel_settings', array("cpun"=>dt_cpanel_crypt(sanitize_user($_POST['cpun']),1),'cppw'=>dt_cpanel_crypt(urlencode($_POST['cppw']),1)), '', 'yes' );
        	echo("<meta http-equiv='refresh' content='0'>");
	} else {
		$cpun_value = '';
		$cppw_value = '';
		if (isset($options['cpun']) && $options['cpun'] != '') $cpun_value = dt_cpanel_crypt($options['cpun']);
		if (isset($options['cppw']) && $options['cppw'] != '') $cppw_value = urldecode(dt_cpanel_crypt($options['cppw']));
		echo "<form method=post>"; ?>
			<th scope="row">cPanel Username:</th><td><input type="text" name="cpun" value="<? echo $cpun_value; ?>"></td><BR>
			<th scope="row">cPanel Password:</th><td><input type="password" name="cppw" value="<? echo $cppw_value; ?>"></td>
			<input type=hidden name=settings_update value=1><?
			submit_button();
		echo "</form>";
	}
}

function dt_cpanel_error_notice($err_string, $exit = 0) {
    ?>
    <div class="error notice">
        <p><?php _e($err_string, 'dt-cpanel-error' ); ?></p>
    </div>
    <?php
	if ($exit) 
		exit;
}

// Email Sub Page
function dt_cpanel_email() {
	$options = get_option( 'cpanel_settings' );
	$cPanel = new DTcPanelAPI(dt_cpanel_crypt($options['cpun']), urldecode(dt_cpanel_crypt($options['cppw'])), '127.0.0.1');
	// New style elements, need to move to css at some point
	?><style>
		tr.border_bottom td {  border-bottom:1pt solid black; }
		hr.dark {  border-top:1pt solid black; max-width: 500px; margin-left: 0px;}
		tr.border_bottom_lt td {  border-bottom:1pt solid #ccc; }
	</style><?	
	$_POST['email'] = sanitize_email($_POST['email']);
	if (isset($_POST['email']) && $_POST['email'] != '') 
		echo "<h1>" . __( 'Email Administration ('.$_POST['email'].')', 'dt-cpanel-email' ) . "</h1>";
	else
		echo "<h1>" . __( 'Email Administration', 'dt-cpanel-email' ) . "</h1>";
    
	// Delete Operation Submitted
	if (isset($_POST['delete']) && $_POST['delete'] == 1) {
		$_POST['delemail'] = sanitize_email($_POST['delemail']);
		list($user, $domain) = explode('@', $_POST['delemail']);
        	echo "<BR><B>Attempting to delete $user@$domain, please wait...</b><BR>";
		$response = $cPanel->UserManager->delete_user([
			'username'        => "$user",
			'domain'          => "$domain"
		]);
		if (isset($response->errors[0]) && $response->errors[0] != ''){
			dt_cpanel_error_notice($response->errors[0],1);
		}
		die("<meta http-equiv='refresh' content='0'>");
        }

	// Create Operation Submitted
	if (isset($_POST['create']) && $_POST['create'] == 1) {
		if (isset($_POST['password']) && $_POST['password'] != '')
			$pass = $_POST['password'];
		else {
			dt_cpanel_error_notice("Password cannot be blank when creating an account. Please try again.<BR><a href=\"?page=dt-cpanel-email\">Back</a>");
			exit;
		}
		
		$user = sanitize_user($_POST['user']);
		$domain = sanitize_user($_POST['domain']);
		if (isset($_POST['max']) && $_POST['max'] == 1)
			$quota = 0;
		else 
			$quota = round($_POST['quota'],0);

		echo "<B>Attempting to create $user@$domain, please wait...</a><BR>";
		$response = $cPanel->UserManager->create_user([
			'domain'                            => "$domain",
			'password'                          => "$pass",
			'services.email.enabled'            => '1',
			'services.email.quota'              => "$quota",
			'services.email.send_welcome_email' => '1',
			'username'                          =>"$user"
		]);
		if (isset($response->errors[0]) && $response->errors[0] != ''){
			dt_cpanel_error_notice($response->errors[0],1);
		}
		die("<meta http-equiv='refresh' content='0'>");
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
	   	echo "<form method=post><tr><td valign=top>$user@$domain<input type=hidden name=email value=$user@$domain></td><td><input name=password type=textbox><BR>(Leave blank to not change)</td>";
		echo "<td><input type=textbox id=quota name=quota value=$quota $disabled><BR><input onchange=\"document.getElementById('quota').disabled = this.checked;\" type=checkbox $checked name=max value=1> Unlimited Storage</td><td valign=top><input type=hidden value=1 name=update><input type=submit value=Update></td></form>";
		echo "<form method=post onsubmit=\"return confirm('Do you really want to delete $user@$domain?');\"><td valign=top><input type=hidden name=delete value=1><input type=hidden name=delemail value=$user@$domain><input type=submit value=Delete></td></form></tr>";
		echo "</table><BR><a href=\"?page=dt-cpanel-email\">Back</a>";
		exit;
	}

	// Update Operation Submitted
	if (isset($_POST['update']) && $_POST['update'] == 1) {
		if (isset($_POST['max']) && $_POST['max'] == 1)
			$quota = 0;
		else
			$quota = round($_POST['quota'],0);
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
				dt_cpanel_error_notice($response->errors[0],1);
			}
		}
		$response = $cPanel->Email->edit_pop_quota([
		        'email'           => "$user",
	        	'quota'           => "$quota",
		        'domain'          => "$domain"
		]);
		if (isset($response->errors[0]) && $response->errors[0] != ''){
			dt_cpanel_error_notice($response->errors[0],1);
		}
		die("<meta http-equiv='refresh' content='0'>");
	}


	// Default Page Display Output
	$response = $cPanel->Email->list_pops_with_disk();
	if (isset($response->errors[0]) && $response->errors[0] != ''){
		dt_cpanel_error_notice($response->errors[0],1);
	}
	echo "<BR></GR><h2>Current Email Accounts:</h2><table width=50%>";
        echo "<tr class=border_bottom><td width=200px><B>Email Account</b><td><b>Disk Used</b></td><td><b>Disk Quota</b></td><td></td></tr>";
	// Init Counter for loop
	$c=0;
	if (sizeof($response->data) > 0) {
		foreach ($response->data as $data) {
			if (filter_var($data->login, FILTER_VALIDATE_EMAIL)) {
        	        	$odata[$c][0] = $data->login;
				$odata[$c][1] = $data->humandiskused;
				$odata[$c][2] = $data->humandiskquota;
				$c++;
			}
		}
		// Alphabetize our list of email addresses for ease of finding them
		// ToDo: Add pages for lists and a default setting for number of elements listed
		sort($odata);
		for ($i=0;$i<sizeof($odata);$i++) {
			echo "<tr class=border_bottom_lt><td>".$odata[$i][0]."</td><td>".$odata[$i][1]."</td><td>".$odata[$i][2]."</td>";
			echo "<form method=post><td><input type=hidden name=manage value=1><input type=hidden name=email value=".$odata[$i][0]."><input type=hidden name=quota value=".$odata[$i][2]."><input type=submit value=Manage></td></form></tr>";
		}
		echo "</table><BR>$i of $i Emails Displayed.";
	} else 
		echo "No Email Accounts to Display<BR>";


	// Create new Email Form
	$domain_list = dt_cpanel_getDomainList();
   	echo "<form method=post><BR><BR><BR><BR><HR class=dark><h2><b>Create New Email Address:</h2></b><table>";
	echo "<tr><td>Email Address:</td><td><input autocomplete=lolwhut type=textbox name=user>@<select name=domain>";
		foreach($domain_list as $dom) {
			echo "<option value=$dom>$dom</option>";
		}
	echo "</select></td></tr>
                <tr><td>Email Password:</td><td><input name=password type=textbox></td></tr>
                <tr><td valign=top>Email Quota (in MB):</td><td><input type=textbox id=quota name=quota value=2048><BR><input onchange=\"document.getElementById('quota').disabled = this.checked;\" type=checkbox name=max value=1> Unlimited Storage</td></tr></table><input type=hidden value=1 name=create><input type=submit value=Create>
	</form>";
}



// Encrypt and Decryption function for cpanel credentials using OpenSSL
function dt_cpanel_crypt($string,$action = false) {
        settings_fields( 'cpanel_key' );
        do_settings_sections( __FILE__ );
	$key = get_option( 'cpanel_key' );
	$cipher = "aes-256-cbc";
	if (in_array($cipher, openssl_get_cipher_methods())) {
		$iv = substr( hash( 'sha256', $key['key1'] ), 0, 16 );
		if ($action) {
			$output = openssl_encrypt($string, $cipher, $key['key2'], $options=0, $iv);
		} else {
			$output = openssl_decrypt($string, $cipher, $key['key2'], $options=0, $iv);
		}
	} else {
	        add_settings_error('cpanel_settings', 'cpanel_invalid_entry', 'Invalid cipher! Please check that your host supports openssl using aes-256-cbc.', $type = 'error');
	}
	return $output;
}

function dt_cpanel_uninstallPlugin() {
	if (!defined('WP_UNINSTALL_PLUGIN')) {
		die;
	}
	delete_option('cpanel_key');
	delete_option('cpanel_settings');
}

?>
