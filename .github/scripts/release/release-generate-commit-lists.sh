#!/usr/bin/env bash
set -euo pipefail

# Inputs
# BASE_SHA is latest stable release bundle tag commit sha
# HEAD_SHA is release or hotfix branch head sha
BASE_SHA="$1"
HEAD_SHA="$2"

echo "[DEBUG] Using BASE_SHA $BASE_SHA and HEAD_SHA $HEAD_SHA to build commit list..."

# Paths to identify changes in components
echo "[DEBUG] Using $(dirname "$0")/release-component-paths.sh for paths."
source "$(dirname "$0")/release-component-paths.sh"

# Prepare list of commit-list
mkdir -p artifacts/commit-lists

# Generate list of commits for changed components list
while read -r component; do
  echo "Generating commit list for $component"

  paths="${COMPONENT_PATHS[$component]}"

  echo "[DEBUG] Paths to consider for $component are: $paths"

  if [ -z "$paths" ]; then
    echo "No paths defined for $component, skipping"
    continue
  fi

  # Add commits to component commit list
  echo "Adding commits for $component to file artifacts/commit-lists/${component}.txt"
  git log \
    --pretty=format:'%h %s' \
    "$BASE_SHA..$HEAD_SHA" \
    -- $paths \
    > "artifacts/commit-lists/${component}.txt"

  # Checking content
  echo -e "Commits added for $component are: \r\n"
  cat "artifacts/commit-lists/${component}.txt"

done < artifacts/changed-components.txt