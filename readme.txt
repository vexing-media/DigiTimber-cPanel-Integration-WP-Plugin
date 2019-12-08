=== DigiTimber Integration Plugin for cPanel ===
Contributors: digitimber
Donate link: http://www.digitimber.com/wpdonate
Tags: cPanel, email, manage
Requires at least: 5.0
Tested up to: 5.3
Stable tag: 1.2.2

== Description ==

DigiTimber Integration Plugin for cPanel allows users to access basic cPanel functionality from within WordPress. This plugin was created initially for our own user, but decided that with the lack of any other plugins in the list, we'd toss it out there for others. Hopefully its helpful to you and your users!

Currently limited to email administration, but more is planned.

= Email Accounts =

- View a list of all email accounts for all domains.
- Add a new email accounts to any domain registered in cpanel.
- Update email account passwords and quotas.
- Delete email accounts.


In time we are hoping to add many functions from within the WordPress site that users would otherwise need to log into cPanel in order to access.

== Frequently Asked Questions ==

= Is it secure to have my cPanel login credentials in my WordPress? =

It's as secure as your wordpress site. We store the credentials using AES-256 encryption in the WordPress database. The salt and iv are computed once on installation so each installation is unique. 

= Is there an undo option? =

No. Unfortunately all changes made are immediately caried out on the server. Data loss may occur if you use the delete or modify options. Please ensure you have a valid backup of your data on cPanel while using any remote plugin.

= Where is all the documentation? =

Currently there is no documentation besides this readme. More will become available as we add additional functionality.

= Do you make any other plugins? =

Not at this time. 

== Changelog ==

= 1.2.2 = 12/8/2019
- INFO: Initial Submission to WordPress Official Plugins List
- ADDED: Created this file, readme.txt
- ADDED: Addon Email management - lists Emails / add new email accounts / modify email accounts / delete email accounts
- UPDATED: Encrypt cPanel credentials for storage in the database using AES-256 with generated key and iv

= 1.1.0 = 12/8/2019
- INFO: Added 3rd version identifier for security and patch updates. New format is Major.Minor.Patch
- UPDATED: Encrypt cPanel credentials for storage in the database using basic encryption and static key and iv

= 1.0 = 12/1/2019
- ADDED: Email listings - ability to add and delete
- ADDED: First savings of settings in database, plain text
- INFO: First Release

