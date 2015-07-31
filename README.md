Mozilla Sync app
================

Simple ownCloud app integrating the Mozilla Sync API.
It allows users to synchronize Firefox data (e.g. bookmarks, passwords, history,...) using their ownCloud server.

### Content
1. <a href="#installation">Installation</a>
2. <a href="#manual">Manual</a>
3. <a href="#helpful-hints">Helpful hints</a>
    1. <a href="#firefox-on-android">Firefox on Android</a>
    2. <a href="#ldap">LDAP</a>
4. <a href="#maintainers">Maintainers</a>
5. <a href="#api">API</a>

Installation
------------
For install, re-/uninstall and upgrade instruction look at [Changing your Mozilla Sync installation](INSTALL.md).

Manual
------
Information for Mozilla Sync users can be found in the [User Manual](docs/USER.md). Information for ownCloud admins can be found in the [Admin Manual](docs/ADMIN.md).

Helpful hints
-------------

### Firefox on Android

Older versions of Firefox on Android used only the ````RC4-SHA```` SSL cipher suite. This has been fixed and "TLS_DHE_RSA_WITH_AES_256_CBC_SHA" and "TLS_DHE_RSA_WITH_AES_128_CBC_SHA" have been added to Firefox 29

Mozilla has a document listing the [recommended TLS ciphers](https://wiki.mozilla.org/Security/Server_Side_TLS#Recommended_Ciphersuite).

Firefox Sync on Android does not support SNI. A workaround is to disable it for the domain owncloud is using.
Use this in your ```nginx.conf```:
```
listen 443 default_server ssl;
```

Additionally, if you are using a self-signed SSL certificate you need to import it to Android via:
*Settings → Security → Install from storage*. Note that Android will only import self-signed certificates with the CA bit set.
The import was successful when you see your certificate in *Settings → Security → Trusted credentials*.

### LDAP
If you want to use Mozilla Sync with an LDAP backend, make sure that you enable email login. To do this set the LDAP user login filter in your admin panel to e.g. ```(|(uid=%uid)(mail=%uid))```.

Furthermore, you need to set the special attribute ```Email``` in your LDAP configuration. See the [ownCloud manual](http://doc.owncloud.org/server/5.0/admin_manual/configuration/auth_ldap.html#special-attributes) for more information.

Maintainers
-----------
Mozilla Sync is currently maintained by [@ogasser](https://github.com/ogasser).
It was originally developed and maintained by Michal Jaskurzynski ([@jaskoola](https://github.com/jaskoola)).

API
---
The Mozilla Sync API is documented on Mozilla's wiki:
* [Sync Client Documentation](http://docs.services.mozilla.com/sync/index.html)
* [The Life of a Sync](http://docs.services.mozilla.com/sync/lifeofasync.html)
* [Global Storage Version 5](http://docs.services.mozilla.com/sync/storageformat5.html)
* [Storage API v1.1](http://docs.services.mozilla.com/storage/apis-1.1.html)
* [User API v1.0](https://docs.services.mozilla.com/reg/apis.html)
