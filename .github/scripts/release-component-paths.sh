#!/usr/bin/env bash

declare -A COMPONENT_PATHS

COMPONENT_PATHS[centreon-awie]="
centreon-awie
.version.centreon-awie
"

COMPONENT_PATHS[centreon-dsm]="
centreon-dsm
.version.centreon-dsm
"

COMPONENT_PATHS[centreon-ha]="
centreon-ha
.version.centreon-ha
"

COMPONENT_PATHS[centreon-open-tickets]="
centreon-open-tickets
.version.centreon-open-tickets
"

COMPONENT_PATHS[centreon-web]="
centreon
.version.centreon-web
"
