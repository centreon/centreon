#!/bin/sh

set -ex

VERSION=$(date '+%Y%m%d')
RELEASE=$(date '+%H%M%S')
PLUGIN_NAME="$3"

# Get committer.
COMMIT=$(git log -1 HEAD --pretty=format:%h)
COMMITTER=$(git show --format='%cN <%cE>' HEAD | head -n 1)

# Process plugin
perl ci/scripts/plugins-source.container.pl $PLUGIN_NAME "$VERSION ($COMMIT)"
cd ..

# Process specfile
#python3 ci/scripts/create-spec-file.py $PLUGIN_NAME


exit 0
