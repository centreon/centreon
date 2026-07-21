#!/usr/bin/env bash
set -euo pipefail

# Inputs
# BASE_SHA is latest stable release bundle tag commit sha
# HEAD_SHA is release or hotfix branch head sha
BASE_SHA="$1"
HEAD_SHA="$2"

echo "[DEBUG] Using BASE_SHA $BASE_SHA and HEAD_SHA $HEAD_SHA for diff..."

# Paths to identify changes in components
source "$(dirname "$0")/release-component-paths.sh"

# Prepare list of changed-components
mkdir -p artifacts
touch artifacts/changed-components.txt

# Map git diff output of BASE_SHA <> HEAD_SHA to array
mapfile -t CHANGED_FILES < <(
  git diff --name-only "$BASE_SHA" "$HEAD_SHA"
)

# Declare COMPONENT_CHANGED array
declare -A COMPONENTS_CHANGED

# Flag component as changed if one of its paths has changed
for file in "${CHANGED_FILES[@]}"; do
  echo "Checking changes for file $file ..."
  for component in "${!COMPONENT_PATHS[@]}"; do
    for path in ${COMPONENT_PATHS[$component]}; do
      #echo "Checking change(s) for file $file of component $component in path $path ..."
      if [[ "$file" == "$path" || "$file" == "$path/"* ]]; then
        echo "Component $component has changes in file $file at component path $path"
        echo "Marked $component as changed."
        COMPONENTS_CHANGED[$component]=1
      fi
    done
  done
done

# Generate finale list of changed components
for component in "${!COMPONENTS_CHANGED[@]}"; do
  echo "$component" >> artifacts/changed-components.txt
done

# Sort by name for easier usage
sort -u artifacts/changed-components.txt -o artifacts/changed-components.txt

# Output
echo -e "Detected changes on components: \r\n$(cat artifacts/changed-components.txt)"
