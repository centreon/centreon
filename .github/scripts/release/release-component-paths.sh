#!/usr/bin/env bash

declare -A COMPONENT_PATHS

COMPONENT_PATHS[centreon]="
.version.centreon-web
packaging/centreon-web
"

COMPONENT_PATHS[centreon-awie]="
awie
.version.centreon-awie
packaging/centreon-awie
"

COMPONENT_PATHS[centreon-dsm]="
dsm
.version.centreon-dsm
packaging/centreon-dsm
"

COMPONENT_PATHS[centreon-open-tickets]="
open-tickets
.version.centreon-open-tickets
packaging/centreon-open-tickets
"

COMPONENT_PATHS[centreon-ha]="
ha
.version.centreon-ha
packaging/centreon-ha
"