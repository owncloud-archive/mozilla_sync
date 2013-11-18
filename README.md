Mozilla Sync app
================

Description:
------------
Simple ownCloud app integrating the Mozilla Sync API.
It allows users to synchronize Firefox data (e.g. bookmarks, passwords, history,...) using their ownCloud server.

Firefox on Android:
-------------------
Firefox on Android uses the ````RC4-SHA```` SSL cipher suite.
This must be enabled on your server in order for synchronization to work with Firefox on your smartphone or tablet.

If you use *nginx* you can add the following to your ````nginx.conf````:
````
ssl_ciphers HIGH:RC4-SHA:!aNULL:!MD5;
````

If you use *apache* you can add the following to your ````apache2.conf````:
````
SSLCipherSuite HIGH:RC4-SHA:!aNULL:!MD5
````

For more info see the [nginx](http://wiki.nginx.org/HttpSslModule#ssl_ciphers) and [apache2](https://httpd.apache.org/docs/2.2/mod/mod_ssl.html#sslciphersuite) documentation.

Additionally, if you are using a self-signed SSL certificate you need to import it to Android via:
*Settings → Security → Install from storage*. Note that Android will only import self-signed certificates with the CA bit set.
The import was successful when you see your certificate in *Settings → Security → Trusted credentials*.

LDAP:
-----
If you want to use Mozilla Sync with an LDAP backend, make sure that you enable email login. To do this set the LDAP user login filter in your admin panel to e.g. ```(|(uid=%uid)(mail=%uid))```.

Maintainers:
------------
Mozilla Sync is currently maintained by [@ogasser](https://github.com/ogasser).
It was originally developed and maintained by Michal Jaskurzynski ([@jaskoola](https://github.com/jaskoola)).

API:
----
The Mozilla Sync API is documented on Mozilla's wiki:
* [Sync Client Documentation](http://docs.services.mozilla.com/sync/index.html)
* [The Life of a Sync](http://docs.services.mozilla.com/sync/lifeofasync.html)
* [Global Storage Version 5](http://docs.services.mozilla.com/sync/storageformat5.html)
* [Storage API v1.1](http://docs.services.mozilla.com/storage/apis-1.1.html)
