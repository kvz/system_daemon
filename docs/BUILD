# dumps the changelog from last saved tag-version till now (HEAD)
./release.php changelog

# Take the time to review docs/CHANGELOG and write up a nice human
# readable docs/NOTES

# Build a new version.
./release.php bake v0.9.3-beta

# This:
#  Builds package
#  Adds everything to git
#  Creates a new tag with the version you've given.

# Now test package with:
cd packages
pear install -O System_Daemon-0.9.3-beta.tgz

# Upload it at
# http://pear.php.net/release-upload.php
