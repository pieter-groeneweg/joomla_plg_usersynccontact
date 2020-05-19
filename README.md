# joomla_plg_usersynccontact
Plugin to sync user profile data to linked contact details

When creating a new user with the plugin "User - Contact Creator" enabled, a User is automatically added as Contact.
When plugin "User - Profile" is enabled, nothing of the profile data is stored in Contact Details.

This plugin makes sure the data is synced from User -> Contact.
It does NOT sync reverse. This is of no use as admins can eventually add more Contacts to a User. It would create "off-sync" situations.

But as registered users on login may be able to change their profile (user profile that is), this will automatically be synced to their Contact details.

--note: 
- translation files have not yet been created.
- instructions are in the configuration of the plugin. More explanatory to follow.
