#!/usr/bin/env bash
set -euo pipefail

case "$DISTRIB_FAMILY" in
  el) ROOT_REPO="rpm-standard" ;;
  debian) ROOT_REPO="apt-standard" ;;
  ubuntu) ROOT_REPO="ubuntu-standard" ;;
  *)
    echo "::error::Unsupported distribution family: $DISTRIB_FAMILY"
    exit 1
    ;;
esac

if [[ "$IS_CLOUD" == "true" ]]; then
  ROOT_REPO="$ROOT_REPO-internal"
fi

TESTING_SEGMENT="testing-$RELEASE_TYPE"
TRACKING_REPOSITORY_NAME="$ROOT_REPO-$MAJOR_VERSION-$DISTRIB-$TESTING_SEGMENT-$MODULE_NAME"

STABLE_REPOSITORY_PREFIX=""
STABLE_BASE_PATH_PREFIX=""
REPOSITORY_NAME=""
BASE_PATH=""
STABLE_SUITE=""

if [[ "$DISTRIB_FAMILY" == "el" ]]; then
  STABLE_REPOSITORY_PREFIX="$ROOT_REPO-$MAJOR_VERSION-$DISTRIB-stable"
  STABLE_BASE_PATH_PREFIX="$ROOT_REPO/$MAJOR_VERSION/$DISTRIB/stable"
else
  REPOSITORY_NAME="$ROOT_REPO"
  BASE_PATH="$ROOT_REPO"
  STABLE_SUITE="$DISTRIB-$MAJOR_VERSION-stable"
fi

echo "[DEBUG] - tracking_repository_name: $TRACKING_REPOSITORY_NAME"
echo "[DEBUG] - stable_repository_prefix: $STABLE_REPOSITORY_PREFIX"
echo "[DEBUG] - stable_base_path_prefix: $STABLE_BASE_PATH_PREFIX"
echo "[DEBUG] - repository_name: $REPOSITORY_NAME"
echo "[DEBUG] - base_path: $BASE_PATH"
echo "[DEBUG] - stable_suite: $STABLE_SUITE"

{
  echo "tracking_repository_name=$TRACKING_REPOSITORY_NAME"
  echo "stable_repository_prefix=$STABLE_REPOSITORY_PREFIX"
  echo "stable_base_path_prefix=$STABLE_BASE_PATH_PREFIX"
  echo "repository_name=$REPOSITORY_NAME"
  echo "base_path=$BASE_PATH"
  echo "stable_suite=$STABLE_SUITE"
} >> "$GITHUB_OUTPUT"
