Release Management
==================

This document lists the things to do before uploading a new release to [apps.owncloud.com](http://apps.owncloud.com/content/show.php/Mozilla+Sync?content=161793):

1. Test whether new release works properly.
2. Merge new release branch into the *master* branch.
3. Read new language strings ```l10n.pl mozilla_sync read``` and add template to the *master* branch.
4. Wait until transifex pushes the translated strings to the *master* branch.
5. Add changes since last version to the [CHANGELOG](CHANGELOG).
6. Update version in ```appinfo/version```.
7. Update release date in [CHANGELOG](CHANGELOG).
8. ```make``` new version.
9. Upload new version to [apps.owncloud.com](http://apps.owncloud.com/content/show.php/Mozilla+Sync?content=161793).
10. Add ```git tag``` to the *master* branch.

