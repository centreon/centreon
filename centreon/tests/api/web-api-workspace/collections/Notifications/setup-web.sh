#!/bin/bash
# Specific setup for the Notifications collection.
#
# The `/configuration/notifications` endpoints are gated by the
# `notification` feature flag. In `config/features.json` the flag is
# set to `2` (BIT_CLOUD only), so it is disabled by default on the
# OnPrem image used in CI, and every request to /configuration/notifications
# returns 404.
#
# To run the Bruno collection we need the flag enabled on OnPrem too.
# We rewrite the value to `3` (BIT_ON_PREM | BIT_CLOUD) inside the
# `web` container only — this does not impact other collections since
# each bruno-test job runs in its own ephemeral Docker container.
#
# `FILE_FEATURE_FLAGS` resolves to `%centreon_path%/config/features.json`
# (cf. config/services.yaml). `%centreon_path%` is `/usr/share/centreon`
# on a standard install.

set -eu

FEATURE_FLAGS_FILE=/usr/share/centreon/config/features.json

if [[ ! -f "$FEATURE_FLAGS_FILE" ]]; then
    echo "ERROR: $FEATURE_FLAGS_FILE not found"
    exit 1
fi

echo "Enabling 'notification' feature flag for OnPrem in $FEATURE_FLAGS_FILE"
sed -i 's/"notification": *[0-9]\+/"notification": 3/' "$FEATURE_FLAGS_FILE"

# Symfony caches the feature flags container parameters. Clear it so the
# updated value is picked up by subsequent HTTP requests.
if [[ -d /var/cache/centreon ]]; then
    rm -rf /var/cache/centreon/*
fi

echo "Notifications setup-web.sh: done."
