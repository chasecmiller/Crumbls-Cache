# Crumbls-Cache
Caching for Wordpress via PHPFastCache
Not for production.

This is a caching plugin in early beta.  

The goal is to allow a user to support multiple cache types depending on the data type.  Page caching may support one mechanisim, object another, transients another, etc.  That will allow an advanced user to fine tune their caching settings.

Looking for testers.  If you have one of these mechanisms listed in the issues list and would like to help, please shoot me an email or fork away.    If you have something that you'd like to see implemented, please do the same.   Looking for language file builders, testers, etc.

This plugin isn't written to minimize, merge, etc css and js files.  Our only goal for now is to provide a rapid caching mechanisim.

It is currently running on a news site with about 400k posts and about one million visits per month.

Right now, the install isn't automatic and is not made for beginners.  You're going to need to install the plugin then move the advanced-cache.php and object-cache.php into your wp-content directories.  You also will need to set the WP_CACHE constant to true in wp-config.php

Thanks to:
[@khoaofgod](https://github.com/khoaofgod) & [@Geolim4](https://github.com/Geolim4) of [PHP.Social](https://github.com/PHPSocialNetwork) for PHPFastCache.

[@mxmkmarquette](https://github.com/mxmkmarquette/) for helping with memcache testing.

[Advanced Wordpress on Facebook](https://www.facebook.com/groups/advancedwp/) for providing a place for Q&A for advanced WordPress topics.
