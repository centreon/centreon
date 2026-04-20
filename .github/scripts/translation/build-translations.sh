#!/bin/bash
set -euo pipefail

# Build translations for centreon-web.
#
# Expected environment variables (set via workflow env block):
#   IS_NIGHTLY                    - "true" or "false"
#   STABILITY                     - "stable" or "unstable"
#   EVENT_NAME                    - github.event_name
#   HAS_UPDATE_TRANSLATIONS_LABEL - "true" or "false"
#   GITHUB_WORKSPACE              - GitHub Actions workspace root
#   GITHUB_OUTPUT                 - GitHub Actions step output file

TRANSLATIONS_PATCH_FILE=""

git config --global --add safe.directory "$GITHUB_WORKSPACE"

cd centreon

for i in lang/*.UTF-8 ; do
  localefull="$(basename "$i")"
  langName="$(echo "$localefull" | cut -d . -f 1)"
  langShortName="$(echo "$localefull" | cut -d _ -f 1)"
  mkdir -p "www/locale/$localefull/LC_MESSAGES"
  bash -e ../.github/scripts/translation/make_translation.sh centreon "$langName"
  msgfmt "lang/$localefull/LC_MESSAGES/messages.po" -o "www/locale/$localefull/LC_MESSAGES/messages.mo" || exit 1
  msgfmt "lang/$localefull/LC_MESSAGES/help.po" -o "www/locale/$localefull/LC_MESSAGES/help.mo" || exit 1
  php bin/centreon-translations.php "$langShortName" "lang/$localefull/LC_MESSAGES/messages.po" "www/locale/$localefull/LC_MESSAGES/messages.ser"
done

mkdir -p www/locale/en_US.UTF-8/LC_MESSAGES
php bin/centreon-translations.php en lang/fr_FR.UTF-8/LC_MESSAGES/messages.po www/locale/en_US.UTF-8/LC_MESSAGES/messages.ser

if [[ "$(git diff --ignore-matching-lines="POT-Creation-Date" | wc -l)" != "0" ]]; then
  # avoid to always update POT-Creation-Date field
  find -type f -regex '.+\.pot?' -exec sed -i -e 's/"POT-Creation-Date.*$/"POT-Creation-Date: 2025-01-01 00:00+0000\\n"/g' {} \;
  if [[ ( "$IS_NIGHTLY" == "false" && "$STABILITY" == "unstable" ) || ( "$EVENT_NAME" == 'pull_request' && "$HAS_UPDATE_TRANSLATIONS_LABEL" == "true" ) ]]; then
    if [[ "$(git log -1 --pretty=format:'%an')" != "technique-ci" ]]; then
      git config user.name "technique-ci"
      git config user.email "technique+ci@centreon.com"
      git status
      git add .
      git commit -m "chore(lang): update translations"
      git push || echo "::warning::Failed to push translations commit (technique-ci may not have push access)."
    else
      echo "::notice::Last commit author is technique-ci. Skipping commit to avoid infinite loop."
      cd ..
      git diff > translations.patch
      TRANSLATIONS_PATCH_FILE="translations.patch"
    fi
  else
    echo "::notice::Translations have been updated, but the workflow is a nightly run or does not have the update-translations label. Skipping commit. Git patch will be available as artifact."
    cd ..
    git diff > translations.patch
    TRANSLATIONS_PATCH_FILE="translations.patch"
  fi
else
  echo "No changes in translations"
fi

echo "translations_patch_file=$TRANSLATIONS_PATCH_FILE" >> "$GITHUB_OUTPUT"
