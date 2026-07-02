#!/usr/bin/env bash
set -euo pipefail

# Mappings Centreon component name
declare -A COMPONENT_NAME_MAPPING=(
  ["centreon-anomaly-detection"]="Ano. Detect."
  ["centreon-autodiscovery"]="AutoDisco"
  ["centreon-bam"]="BAM"
  ["centreon-cloud-business-extensions"]="CCBE"
  ["centreon-cloud-extensions"]="CCE"
  ["centreon-it-edition-extensions"]="CIEE"
  ["centreon-license-manager"]="License Manager"
  ["centreon-map"]="MAP"
  ["centreon-mbi"]="MBI"
  ["centreon-pp-manager"]="PPM"
)

# Mappings Operating Systems
declare -A OPERATING_SYSTEM_MAPPING=(
  [bullseye]="DEBIAN11"
  [bookworm]="DEBIAN12"
  [trixie]="DEBIAN13"
  [alma8]="ALMA8"
  [alma9]="ALMA9"
  [alma10]="ALMA10"
  [rhel8]="RHEL8"
  [rhel9]="RHEL9"
  [rhel10]="RHEL10"
  [jammy]="Ubuntu_2204"
  [noble]="Ubuntu_24_04"
)

# Mappings Databases
declare -A DATABASE_MAPPING=(
  ["mariadb:11.8"]="MARIADB_11_8"
  ["mariadb:10.11"]="MARIADB_10_11"
  ["mariadb:10.5"]="MARIADB_10_5"
  ["mysql:8.0"]="MYSQL_8"
  ["mysql:8.4"]="MYSQL_8_4"
)

# Mappings Browsers
declare -A BROWSER_MAPPING=(
  [chrome]="CHROME"
  [firefox]="FIREFOX"
  [edge]="EDGE"
)

# Mappings Type of Environment
declare -A TYPE_ENVIRONMENT_MAPPING=(
  [docker]="Docker"
  [onprem]="OnPrem"
  [cloud]="CLOUD"
)

# Mappings Type of Test Execution
declare -A TYPE_TEST_EXECUTION_MAPPING=(
  [api]="API"
  [e2e]="E2E"
  [robotframework]="Generic"
)

# Input parameters with defaults for testing
INPUT_COMPONENT_NAME="${1:-none}"
INPUT_OPERATING_SYSTEM="${2:-alma9}"
INPUT_DATABASE="${3:-mysql:8.0}"
INPUT_BROWSER="${4:-chrome}"
INPUT_TYPE_ENVIRONMENT="${5:-onprem}"
INPUT_TYPE_TEST_EXECUTION="${6:-E2E}"

# Normalize values using mappings, defaulting to "UNKNOWN" if not found
NORMALIZED_COMPONENT_NAME="${COMPONENT_NAME_MAPPING[$INPUT_COMPONENT_NAME]:-UNKNOWN_COMPONENT}"
NORMALIZED_OPERATING_SYSTEM="${OPERATING_SYSTEM_MAPPING[$INPUT_OPERATING_SYSTEM]:-UNKNOWN_OPERATING_SYSTEM}"
NORMALIZED_DATABASE="${DATABASE_MAPPING[$INPUT_DATABASE]:-UNKNOWN_DATABASE}"
NORMALIZED_BROWSER="${BROWSER_MAPPING[$INPUT_BROWSER]:-UNKNOWN_BROWSER}"
NORMALIZED_TYPE_ENVIRONMENT="${TYPE_ENVIRONMENT_MAPPING[$INPUT_TYPE_ENVIRONMENT]:-UNKNOWN_TYPE_ENVIRONMENT}"
NORMALIZED_TYPE_TEST_EXECUTION="${TYPE_TEST_EXECUTION_MAPPING[$INPUT_TYPE_TEST_EXECUTION]:-UNKNOWN_TYPE_TEST_EXECUTION}"

echo "Component Name: $INPUT_COMPONENT_NAME"
echo "Normalized Component Name: $NORMALIZED_COMPONENT_NAME"

echo "Operating System: $INPUT_OPERATING_SYSTEM"
echo "Normalized Operating System: $NORMALIZED_OPERATING_SYSTEM"

echo "Database: $INPUT_DATABASE"
echo "Normalized Database: $NORMALIZED_DATABASE"

echo "Browser: $INPUT_BROWSER"
echo "Normalized Browser: $NORMALIZED_BROWSER"

echo "Type of Environment: $INPUT_TYPE_ENVIRONMENT"
echo "Normalized Type of Environment: $NORMALIZED_TYPE_ENVIRONMENT"

echo "Type of Test Execution: $INPUT_TYPE_TEST_EXECUTION"
echo "Normalized Type of Test Execution: $NORMALIZED_TYPE_TEST_EXECUTION"

# Export GitHub outputs
echo "xray_normalized_component_name=$NORMALIZED_COMPONENT_NAME" >> "$GITHUB_OUTPUT"
echo "xray_normalized_operating_system=$NORMALIZED_OPERATING_SYSTEM" >> "$GITHUB_OUTPUT"
echo "xray_normalized_database_name=$NORMALIZED_DATABASE" >> "$GITHUB_OUTPUT"
echo "xray_normalized_browser=$NORMALIZED_BROWSER" >> "$GITHUB_OUTPUT"
echo "xray_normalized_type_environment=$NORMALIZED_TYPE_ENVIRONMENT" >> "$GITHUB_OUTPUT"
echo "xray_normalized_type_test_execution=$NORMALIZED_TYPE_TEST_EXECUTION" >> "$GITHUB_OUTPUT"