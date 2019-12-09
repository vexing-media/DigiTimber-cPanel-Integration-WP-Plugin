<?php
/*
Plugin Name: DigiTimber Integration Plugin for cPanel
Plugin URI: http://www.digitimber.com/cpanel-wordpress-plugin
Description: Access basic cPanel functions (currently limited to email) from within WordPress. This allows your customers to use the interface that they already know and love to perform basic admin tasks.
Version: 1.2.2a
Author: DigiTimber
Author URI: http://www.digitimber.com/
License:     GPL2
 
DigiTimber Integration Plugin for cPanel is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
any later version.
 
DigiTimber Integration Plugin for cPanel is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
 
You should have received a copy of the GNU General Public License
along with DigiTimber Integration Plugin for cPanel. If not, see https://www.gnu.org/licenses/gpl-3.0.html.
*/


require_once("dtcpaneluapi.class.php");

register_uninstall_hook(__FILE__, 'uninstallPlugin');
add_action( 'admin_menu', 'digitimber_cpanel_menu' );  
function digitimber_cpanel_menu() {
    // Add Toplevel
    add_menu_page(__('DigiTimber cPanel','digitimber-cpanel'), __('DigiTimber cPanel','digitimber-cpanel'), 'administrator', 'dt-top-level-handle', 'dt_toplevel_page', 'dashicons-admin-tools' );

    // Add a submenu for Email
    add_submenu_page('dt-top-level-handle', __('Email','digitimber-cpanel-email'), __('Email','digitimber-cpanel-email'), 'administrator', 'dt-email', 'dt_email');

    // Add a submenu for Settings (Also create a Settings -> cPanel Settings section)
    add_submenu_page('dt-top-level-handle', __('Settings','dt-cpanel-settings'), __('Settings','dt-cpanel-settings'), 'administrator', 'dt-cpanel-settings', 'dt_settings_page');
    add_options_page(__('cPanel Settings','digitimber-cpanel'), __('cPanel Settings','digitimber-cpanel'), 'administrator', 'dt-cpanel-settings', 'dt_settings_page');

} 
add_action( 'admin_init', 'register_cpanel_settings' );
if( !function_exists("register_cpanel_settings") ) { 
    function register_cpanel_settings() {   
        register_setting( 'cpanel_key', 'cpanel_key' ); 
        register_setting( 'cpanel_settings', 'cpanel_settings' ); 
    } 
}

add_action( 'admin_init', 'createRandomKeys' );
function createRandomKeys() {
	settings_fields( 'cpanel_key' );
	do_settings_sections( __FILE__ );
	$k1 = base64_encode(openssl_random_pseudo_bytes(32));
	$k2 = base64_encode(openssl_random_pseudo_bytes(64));
	add_option( 'cpanel_key', array("key1"=>$k1,"key2"=>$k2), '', 'yes' );
}

function dt_toplevel_page() {
	$debug = 0;
	settings_fields( 'cpanel_settings' );
	echo "<h2>" . __( 'DigiTimber Integration Plugin for cPanel', 'digitimber-cpanel' ) . "</h2><BR>";
	echo "Plugin Name: DigiTimber Integration Plugin for cPanel<BR>
	Plugin URI: <a href=\"http://www.digitimber.com/cpanel-wordpress-plugin\">http://www.digitimber.com/cpanel-wordpress-plugin</a><BR>
	Description: DigiTimber Integration Plugin for cPanel.<BR>
	Author: DigiTimber<BR>
	Author URI: <a href=\"http://www.digitimber.com/\">http://www.digitimber.com/</a><BR>";
	if ($debug) {
		echo "Debug: <BR><pre>";
		$data = getDomainList();
		print_r($data);
	}

	echo "<BR><BR><a href=\"?page=dt-email\">Email</a><BR><a href=\"?page=dt-cpanel-settings\">Settings</a>";
}
function getDomainList() {
	$cPanel = connectToCpanel();
	$out = $cPanel->uapi->DomainInfo->list_domains();

	// Collect domain data, primary domain is always first
	$domain_data[0] = $out->data->main_domain;
	$alias = $out->data->parked_domains;
	$addon = $out->data->addon_domains;
	$sub = $out->data->sub_domains;
	$c=1;	
	if (sizeof($alias) > 0) { sort($alias); foreach($alias as $domain) { $domain_data[$c] = $domain; $c++; } }
	if (sizeof($addon) > 0) { sort($addon); foreach($addon as $domain) { $domain_data[$c] = $domain; $c++; } }
	if (sizeof($sub) > 0) { sort($sub); foreach($sub as $domain) { $domain_data[$c] = $domain; $c++; } }

	return $domain_data;
}

function dt_settings_page() {
	settings_fields( 'cpanel_settings' );
	do_settings_sections( __FILE__ );
	$options = get_option( 'cpanel_settings' );
	echo "<h2>" . __( 'Settings Page', 'dt-cpanel-settings' ) . "</h2><BR>";

	if (isset($_POST['settings_update']) && $_POST['settings_update'] == 1) {
		echo "<B>Updating settings, please wait...</b><BR>";
		update_option( 'cpanel_settings', array("cpun"=>dtcrypt($_POST['cpun'],1),'cppw'=>dtcrypt($_POST['cppw'],1)), '', 'yes' );
        	echo("<meta http-equiv='refresh' content='0'>");
	} else {
		$cpun_value = '';
		$cppw_value = '';
		if (isset($options['cpun']) && $options['cpun'] != '') $cpun_value = dtcrypt($options['cpun']);
		if (isset($options['cppw']) && $options['cppw'] != '') $cppw_value = dtcrypt($options['cppw']);
		
		echo "<form method=post>"; ?>
			<th scope="row">cPanel Username:</th><td><input type="text" name="cpun" value="<? echo $cpun_value; ?>"></td><BR>
			<th scope="row">cPanel Password:</th><td><input type="password" name="cppw" value="<? echo $cppw_value; ?>"></td>
			<input type=hidden name=settings_update value=1><?
		submit_button();
		echo "</form>";
	}
}

function dt_error_notice($err_string) {
    ?>
    <div class="error notice">
        <p><?php _e($err_string, 'dt-cpanel-settings' ); ?></p>
    </div>
    <?php
}


function connectToCpanel() {
	$options = get_option( 'cpanel_settings' );
	if(!isset($options['cpun']) || !isset($options['cppw'])) {
		dt_error_notice("cPanel credentials appear to be missing, please check your <a href=\"?page=dt-cpanel-settings\">settings</a>.");
		exit;
	}
	$cPanel = new DTcPanelAPI(dtcrypt($options['cpun']), dtcrypt($options['cppw']), '127.0.0.1');
	$checkvalid = $cPanel->uapi->LastLogin->get_last_or_current_logged_in_ip(); 
	if (isset($checkvalid) && $checkvalid != '') {
		return $cPanel;
	} else {
		dt_error_notice("cPanel credentials appear to be invalid, please check your <a href=\"?page=dt-cpanel-settings\">settings</a>.");
		exit;
	}
}

// Email Page
function dt_email() {
	// New style elements, need to move to css at some point
	?><style>
		tr.border_bottom td {  border-bottom:1pt solid black; }
		tr.border_bottom_lt td {  border-bottom:1pt solid #ccc; }
	</style><?	
	// Header
	if (isset($_POST['email']) && $_POST['email'] != '') 
		echo "<h1>" . __( 'Email Administration ('.$_POST['email'].')', 'dt-email' ) . "</h1>";
	else
		echo "<h1>" . __( 'Email Administration', 'dt-email' ) . "</h1>";
	// Attempt connection to cPanel using existing credentials
	$cPanel = connectToCpanel();
    
	// Delete Operation Submitted
	if (isset($_POST['delete']) && $_POST['delete'] == 1) {
		list($user, $domain) = explode('@', $_POST['delemail']);
        	echo "<BR><B>Attempting to delete $user@$domain, please wait...</b><BR>";
		$response = $cPanel->uapi->UserManager->delete_user([
			'username'        => "$user",
			'domain'          => "$domain"
		]);
		die("<meta http-equiv='refresh' content='0'>");
        }

	// Create Operation Submitted
	if (isset($_POST['create']) && $_POST['create'] == 1) {
		if (isset($_POST['password']) && $_POST['password'] != '')
			$pass = $_POST['password'];
		else {
			dt_error_notice("Password cannot be blank when creating an account. Please try again.<BR><a href=\"?page=dt-email\">Back</a>");
			exit;
		}
		
		$user = $_POST['user'];
		$domain = $_POST['domain'];
		if (isset($_POST['max']) && $_POST['max'] == 1)
			$quota = 0;
		else
			$quota = round($_POST['quota'],0);
		if ($quota < 0) 
			$quota = 2048;

		echo "<B>Attempting to create $user@$domain, please wait...</a><BR>";
		$response = $cPanel->uapi->UserManager->create_user([
			'domain'                            => "$domain",
			'password'                          => "$pass",
			'services.email.enabled'            => '1',
			'services.email.quota'              => "$quota",
			'services.email.send_welcome_email' => '1',
			'username'                          =>"$user"
		]);
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
					default: dt_error_notice("Unable to convert quota to MB ($quota_num $postfix). Please submit this error with a bug report."); break;
				}
			}
		}
		if ($quota < 0) 
			$quota = 2048;
		echo "<BR><table width=50%>";
		echo "<tr class=border_bottom><td width=200px><B>Email Account</b></td><td>New Password</td><td><b>Quota in MB</b></td><td></td><td></td></tr>";
	   	echo "<form method=post><tr><td valign=top>$user@$domain<input type=hidden name=email value=$user@$domain></td><td><input name=password type=textbox><BR>(Leave blank to not change)</td>";
		echo "<td><input type=textbox id=quota name=quota value=$quota $disabled><BR><input onchange=\"document.getElementById('quota').disabled = this.checked;\" type=checkbox $checked name=max value=1> Unlimited Storage</td><td valign=top><input type=hidden value=1 name=update><input type=submit value=Update></td></form>";
		echo "<form method=post onsubmit=\"return confirm('Do you really want to delete $user@$domain?');\"><td valign=top><input type=hidden name=delete value=1><input type=hidden name=delemail value=$user@$domain><input type=submit value=Delete></td></form></tr>";
		echo "</table><BR><a href=\"?page=dt-email\">Back</a>";
		exit;
	}

	// Update Operation Submitted
	if (isset($_POST['update']) && $_POST['update'] == 1) {
		if (isset($_POST['max']) && $_POST['max'] == 1)
			$quota = 0;
		else
			$quota = round($_POST['quota'],0);
		if ($quota < 0) 
			$quota = 2048;
		list($user, $domain) = explode('@', $_POST['email']);
		echo "<B>Attempting to update $user@$domain, please wait...</a><BR>";
		if (isset($_POST['password']) && $_POST['password'] != '') {
			$passwd = $_POST['password'];
			$response = $cPanel->uapi->Email->passwd_pop([
			        'email'           => "$user",
	        		'password'        => "$passwd",
		        	'domain'          => "$domain"
			]);
			if (isset($response->errors[0]) && $response->errors[0] != '') {
				dt_error_notice($response->errors[0]."<BR><a href=\"?page=dt-email\">Back</a>");
				exit;
			}
		}
		$response = $cPanel->uapi->Email->edit_pop_quota([
		        'email'           => "$user",
	        	'quota'           => "$quota",
		        'domain'          => "$domain"
		]);
		die("<meta http-equiv='refresh' content='0'>");
	}

	// Default Page Display
	$response = $cPanel->uapi->Email->list_pops_with_disk();
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
		echo "</table>";
	} else 
		echo "No Email Accounts to Display<BR>";


	// Create new Email Form
	$domain_list = getDomainList();
   	echo "<form method=post><BR><BR><b>Create New Email Address: </b><table>";
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
function dtcrypt($string,$action = false) {
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

function uninstallPlugin() {
	if (!defined('WP_UNINSTALL_PLUGIN')) {
		die;
	}
	delete_option('cpanel_key');
	delete_option('cpanel_settings');
}

?>
