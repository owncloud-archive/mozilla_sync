Changing your Mozilla Sync installation
=======================================

Install:
--------

You can either install the stable release version or the development version:

* Stable: [apps.owncloud.com](http://apps.owncloud.com/content/show.php?content=161793)
* Development: Clone [GitHub repository](https://github.com/owncloud/mozilla_sync/) or download [ZIP file of the master branch](https://github.com/owncloud/mozilla_sync/archive/master.zip)

Move the resulting ````mozilla_sync```` folder to your ownCloud's apps directory and enable it in the web interface's admin panel.


Uninstall:
----------

To completely uninstall Mozilla Sync, *deactivate/uninstall* it in your ownCloud's admin panel. Then, drop the following tables in your database:
* ````oc_mozilla_sync_collections````
* ````oc_mozilla_sync_users````
* ````oc_mozilla_sync_wbo````

Finally, delete all four entries related to the app from the ````oc_appconfig```` table by executing the following SQL statement:

````
DELETE FROM oc_appconfig WHERE oc_appconfig.appid = 'mozilla_sync' AND oc_appconfig.configkey = 'types';
DELETE FROM oc_appconfig WHERE oc_appconfig.appid = 'core' AND oc_appconfig.configkey = 'remote_mozilla_sync';
DELETE FROM oc_appconfig WHERE oc_appconfig.appid = 'mozilla_sync' AND oc_appconfig.configkey = 'installed_version';
DELETE FROM oc_appconfig WHERE oc_appconfig.appid = 'mozilla_sync' AND oc_appconfig.configkey = 'enabled';
````

Now you have completely removed Mozilla Sync and are free to do a clean reinstall of the app.


Upgrade:
----------

If you want to upgrade the Mozilla Sync app you can just replace the ````apps/mozilla_sync/```` folder with a newer version.


Reinstall:
----------

To reinstall the app you can first follow the **Uninstall** and then the **Install** sections.
