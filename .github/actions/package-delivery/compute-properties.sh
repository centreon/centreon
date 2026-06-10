#!/usr/bin/env bash
set -euo pipefail

if [[ -z "$MODULE_NAME" || -z "$DISTRIB" || -z "$VERSION" || -z "$STABILITY" || -z "$IS_CLOUD" ]]; then
  echo "::error::some mandatory inputs are empty, please check the logs."
  exit 1
fi

if [[ "$DELIVERY_TYPE" != "feature" && "$STABILITY" != "testing" && "$STABILITY" != "unstable" ]]; then
  echo "::error::stability must be one of: testing, unstable (got '$STABILITY')"
  exit 1
fi

case "$DISTRIB_FAMILY" in
  el) ROOT_REPO="rpm-standard" ;;
  debian) ROOT_REPO="apt-standard" ;;
  ubuntu) ROOT_REPO="ubuntu-standard" ;;
  *)
    echo "::error::Unsupported distribution family: $DISTRIB_FAMILY"
    exit 1
    ;;
esac

STABILITY_SEGMENT="$STABILITY"
if [[ "$STABILITY" == "testing" && ("$RELEASE_TYPE" == "release" || "$RELEASE_TYPE" == "hotfix") ]]; then
  STABILITY_SEGMENT="$STABILITY-$RELEASE_TYPE"
fi

REPOSITORY_PREFIX=""
BASE_PATH_PREFIX=""
REPOSITORY_NAME=""
BASE_PATH=""
SUITE=""
TRACKING_REPOSITORY_NAME=""

if [[ "$DELIVERY_TYPE" == "feature" ]]; then
  if [[ "$DISTRIB_FAMILY" != "el" ]]; then
    echo "::notice::Feature delivery is not supported for $DISTRIB_FAMILY packages, skipping delivery."
    echo "skip_delivery=true" >> "$GITHUB_OUTPUT"
    exit 0
  fi

  FEATURE_TICKET=$(echo "$GH_HEAD_REF" | grep -oE 'MON-[0-9]+' | head -1 || true)
  if [[ -z "$FEATURE_TICKET" ]]; then
    echo "::error::Cannot extract the feature ticket from branch name $GH_HEAD_REF"
    exit 1
  fi

  REPOSITORY_PREFIX="$ROOT_REPO-feature-$FEATURE_TICKET-$VERSION-$DISTRIB-$STABILITY"
  BASE_PATH_PREFIX="$ROOT_REPO-feature-$FEATURE_TICKET/$VERSION/$DISTRIB/$STABILITY"
else
  if [[ "$IS_CLOUD" == "true" ]]; then
    ROOT_REPO="$ROOT_REPO-internal"
  fi

  if [[ "$DISTRIB_FAMILY" == "el" ]]; then
    REPOSITORY_PREFIX="$ROOT_REPO-$VERSION-$DISTRIB-$STABILITY_SEGMENT"
    BASE_PATH_PREFIX="$ROOT_REPO/$VERSION/$DISTRIB/$STABILITY_SEGMENT"
  else
    REPOSITORY_NAME="$ROOT_REPO"
    BASE_PATH="$ROOT_REPO"
    SUITE="$DISTRIB-$VERSION-$STABILITY_SEGMENT"
  fi

  # testing deliveries are also tracked in a module scoped repository
  # so that promote-to-stable can identify which packages belong to this module
  if [[ "$STABILITY" == "testing" ]]; then
    TRACKING_REPOSITORY_NAME="$ROOT_REPO-$VERSION-$DISTRIB-$STABILITY_SEGMENT-$MODULE_NAME"
  fi
fi

echo "[DEBUG] - repository_prefix: $REPOSITORY_PREFIX"
echo "[DEBUG] - base_path_prefix: $BASE_PATH_PREFIX"
echo "[DEBUG] - repository_name: $REPOSITORY_NAME"
echo "[DEBUG] - base_path: $BASE_PATH"
echo "[DEBUG] - suite: $SUITE"
echo "[DEBUG] - tracking_repository_name: $TRACKING_REPOSITORY_NAME"

{
  echo "skip_delivery=false"
  echo "repository_prefix=$REPOSITORY_PREFIX"
  echo "base_path_prefix=$BASE_PATH_PREFIX"
  echo "repository_name=$REPOSITORY_NAME"
  echo "base_path=$BASE_PATH"
  echo "suite=$SUITE"
  echo "tracking_repository_name=$TRACKING_REPOSITORY_NAME"
} >> "$GITHUB_OUTPUT"
