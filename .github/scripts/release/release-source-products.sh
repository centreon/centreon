#!/usr/bin/env bash

# Components of this repository that publish a source tarball to the download site, mapped to the
# bucket directory they publish it to. A component absent from this map publishes no tarball and is
# never waited on by the download-site publication.
#
# The catalog product id is the component name itself; only centreon-web's bucket directory differs
# from it, which is the whole reason this map cannot be derived from the tag.
# Keys match the component tag prefix, so tag centreon-web-25.10.17 -> key centreon-web.

# shellcheck disable=SC2034  # read by the scripts that source this file
declare -A SOURCE_BUCKET_DIRECTORY

SOURCE_BUCKET_DIRECTORY[centreon-web]="centreon"
SOURCE_BUCKET_DIRECTORY[centreon-awie]="centreon-awie"
SOURCE_BUCKET_DIRECTORY[centreon-dsm]="centreon-dsm"
SOURCE_BUCKET_DIRECTORY[centreon-open-tickets]="centreon-open-tickets"

# centreon-ha is deliberately absent: product management and support confirmed its sources are not
# meant to be distributed, even though ha.yml still carries a deliver-sources job that uploads one.
