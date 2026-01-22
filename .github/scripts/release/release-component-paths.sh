#!/usr/bin/env bash

declare -A COMPONENT_PATHS

COMPONENT_PATHS[centreon]="
centreon
.version.centreon-web
packaging/centreon-web
"

COMPONENT_PATHS[centreon-awie]="
centreon-awie
.version.centreon-awie
packaging/centreon-awie
"

COMPONENT_PATHS[centreon-dsm]="
centreon-dsm
.version.centreon-dsm
packaging/centreon-dsm
"

COMPONENT_PATHS[centreon-open-tickets]="
centreon-open-tickets
.version.centreon-open-tickets
packaging/centreon-open-tickets
"

COMPONENT_PATHS[centreon-ha]="
centreon-ha
.version.centreon-ha
packaging/centreon-ha
"