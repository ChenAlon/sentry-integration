#!/bin/bash

# Update the dependencies first so we are up-to-date
composer update --no-dev --optimize-autoloader --no-interaction

# Get the latest tag so we can show it
GIT_LATEST="$(git describe --abbrev=0 --tags)"

# Read the version we are going to release
read -p "Specify a version (ex: 2.0.0) - latest git tag is ${GIT_LATEST}:" version

# Cleanup the old dir if it is there
rm -rf /tmp/wordpress-sentry-integration-plugin-svn

# Checkout the svn repo
svn co http://plugins.svn.wordpress.org/sentry-integration-integration/ /tmp/wordpress-sentry-integration-plugin-svn

echo "Copying files to trunk"
rsync -Rrd --delete --exclude 'release.sh' --exclude ".*" ./ /tmp/wordpress-sentry-integration-plugin-svn/trunk/

cd /tmp/wordpress-sentry-integration-plugin-svn/

svn status | grep '^!' | awk '{print $2}' | xargs svn delete
svn add --force * --auto-props --parents --depth infinity -q

svn status

svn commit -m "Syncing v${version} from GitHub"

echo "Creating release tag"

mkdir /tmp/wordpress-sentry-integration-plugin-svn/tags/${version}
svn add /tmp/wordpress-sentry-integration-plugin-svn/tags/${version}
svn commit -m "Creating tag for v${version}"

echo "Copying versioned files to v${version} tag"

svn cp --parents trunk/* tags/${version}

svn commit -m "Tagging v${version}"