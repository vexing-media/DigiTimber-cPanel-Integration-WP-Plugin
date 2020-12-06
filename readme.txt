=== DigiTimber cPanel Integration ===
Contributors: digitimber
Tags: cPanel, email, manage
Requires PHP: 7.1
Requires at least: 5.0
Tested up to: 5.3
Stable tag: 1.4.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
== Description ==

DigiTimber cPanel Integration allows users to access basic cPanel functionality from within WordPress. This plugin was created initially for our own user, but decided that with the lack of any other plugins in the list, we'd toss it out there for others. Hopefully its helpful to you and your users!

Currently limited to email administration, but more is planned.
- View a list of all email accounts for all domains.
- Add a new email accounts to any domain registered in cpanel.
- Update email account passwords and quotas.
- Delete email accounts.

In time we are hoping to add many functions from within the WordPress site that users would otherwise need to log into cPanel in order to access.

== Installation ==

1. Visit 'Plugins > Add New'
2. Search for 'DigiTimber cPanel Integration'
3. Activate DigiTimber cPanel Integration from your Plugins page.
4. Select Settings -> cPanel Settings to provide username and password to the plugin.

or

1. Download zip from GitHub: https://github.com/vexing-media/DigiTimber-cPanel-Integration-WP-Plugin
2. Visit 'Plugins > Add New -> Upload Plugin
3. Click 'Choose File', select .zip file you downloaded, and click 'Install Now'
4. Activate DigiTimber cPanel Integration from your Plugins page.
5. Select Settings -> cPanel Settings to provide username and password to the plugin.

== Screenshots ==

1. Email Page - Shows the email management page
2. Manage Page - Shows the page of managing a single email account
3. Settings Page - Shows the settings page where you enter cPanel credentials

== Frequently Asked Questions ==

# Is it secure to have my cPanel login credentials in my WordPress? 

It's as secure as your wordpress site. We store the credentials using AES-256 encryption in the WordPress database. The salt and iv are computed once on installation so each installation is unique. 

# Is there an undo option?

No. Unfortunately all changes made are immediately caried out on the server. Data loss may occur if you use the delete or modify options. Please ensure you have a valid backup of your data on cPanel while using any remote plugin.

# Where is all the documentation?

Currently there is no documentation besides this readme. More will become available as we add additional functionality.

# Do you make any other plugins?

Not at this time. 

# How do I contact someone for support of this plugin?

While we don't offer any offical support for this plugin, please email plugin@digitimber.com or post to the WordPress support forum and we will attempt to assist to the best of our abilities. 

== Changelog ==

= 1.3.3 = 2/7/2020
- BUGFIX (Issue#9): Unable to delete email accounts created in cPanel
- ADDED: Created a settings section to allow users to select which domains are seen in the plugin (in case people want to limit for large accounts) (defaults to all enabled)

= 1.3.2 = 12/9/2019
- INFO: After submission to WP Plugin Directory, we had a few things to fix
- UPDATED: Changed the overall name of the plugin to DigiTimber cPanel Integration
- UPDATED: Including your own CURL code - Removed old curl library and wrote our own based on the WP HTTP api
- UPDATED: Generic function (and/or define) names - removed old function names that were not very specific and added (hopefully) appropriate naming (dt_cpanel prefix)
- UPDATED: Please sanitize, escape, and validate your POST calls - reviewed all input and applied applicable sanitation or encoding
- UPDATED: Nonces and user permissions - added wp required nonce fields and validation to user input forms

= 1.2.2 = 12/8/2019
- INFO: Initial Submission to WordPress Official Plugins List
- ADDED: Created this file, readme.txt
- ADDED: Addon Email management - lists Emails / add new email accounts / modify email accounts / delete email accounts
- UPDATED: Encrypt cPanel credentials for storage in the database using AES-256 with generated key and iv
- ADDED: New Github repo

= 1.1.0 = 12/8/2019
- INFO: Added 3rd version identifier for security and patch updates. New format is Major.Minor.Patch
- UPDATED: Encrypt cPanel credentials for storage in the database using basic encryption and static key and iv

= 1.0 = 12/1/2019
- ADDED: Email listings - ability to add and delete
- ADDED: First savings of settings in database, plain text
- INFO: First Release
