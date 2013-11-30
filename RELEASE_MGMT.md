Release Management
==================

This document lists the things to do before uploading a new release to [apps.owncloud.com](http://apps.owncloud.com/content/show.php/Mozilla+Sync?content=161793):

1. Test whether new release works properly.
2. Merge new release branch into the *master* branch.
3. Wait until transifex fetches the new strings from the *master* branch.
4. Wait until transifex pushes the translated strings to the *master* branch.
5. Update version in ```appinfo/info.xml```.
6. Add changes since last version to the [CHANGELOG](CHANGELOG).
7. ```make``` new version.
8. Upload new version to [apps.owncloud.com](http://apps.owncloud.com/content/show.php/Mozilla+Sync?content=161793).
9. Add ```git tag``` to the *master* branch.

