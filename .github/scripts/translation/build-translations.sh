#!/bin/bash
set -euo pipefail

# Build translations for centreon-web.
#
# Expected environment variables (set via workflow env block):
#   IS_NIGHTLY                    - "true" or "false"
#   STABILITY                     - "stable" or "unstable"
#   EVENT_NAME                    - github.event_name
#   HAS_UPDATE_TRANSLATIONS_LABEL - "true" or "false"
#   JIRA_BASE_URL, JIRA_USER_EMAIL, JIRA_API_TOKEN, BRANCH_NAME, RUN_URL,
#   HEAD_REF, PR_BODY, PR_URL     - see get-jira-key.sh and set-jira-status.sh
#   GH_TOKEN                      - see find-translations-pr.sh and create-translations-pr.sh
#   GITHUB_WORKSPACE             - GitHub Actions workspace root
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

if [[ "$(git diff --ignore-matching-lines="POT-Creation-Date" | wc -l)" != "0" ]]; then
  # avoid to always update POT-Creation-Date field
  find -type f -regex '.+\.pot?' -exec sed -i -e 's/"POT-Creation-Date.*$/"POT-Creation-Date: 2025-01-01 00:00+0000\\n"/g' {} \;
  if [[ ( "$IS_NIGHTLY" == "false" && "$STABILITY" == "unstable" ) || ( "$EVENT_NAME" == 'pull_request' && "$HAS_UPDATE_TRANSLATIONS_LABEL" == "true" ) ]]; then
    if [[ "$(git log -1 --pretty=format:'%an')" == "technique-ci" ]]; then
      echo "::notice::Last commit author is technique-ci. Skipping commit to avoid infinite loop."
      cd ..
      git diff > translations.patch
      TRANSLATIONS_PATCH_FILE="translations.patch"
    elif open_pr_url="$(bash ../.github/scripts/translation/find-translations-pr.sh || true)" && [[ -n "$open_pr_url" ]]; then
      # avoid a new ticket and pull request on every run while the previous ones are not merged
      echo "::notice::A translation pull request is already open: ${open_pr_url}. Skipping commit."
      cd ..
      git diff > translations.patch
      TRANSLATIONS_PATCH_FILE="translations.patch"
    else
      git config user.name "technique-ci"
      git config user.email "technique+ci@centreon.com"
      git status
      git add .
      # a Jira failure must not prevent the commit
      jira_key="$(bash ../.github/scripts/translation/get-jira-key.sh centreon || true)"
      jira_key_pattern='^[A-Z][A-Z0-9_]+-[0-9]+$'
      if [[ "$jira_key" =~ $jira_key_pattern ]]; then
        git commit -m "chore(lang): update translations" -m "Refs: $jira_key"
      else
        git commit -m "chore(lang): update translations"
      fi
      if git push; then
        # the ticket of a pull request is left to its owner
        if [[ "$jira_key" =~ $jira_key_pattern && "${HEAD_REF:-} ${PR_BODY:-}" != *"$jira_key"* ]]; then
          bash ../.github/scripts/translation/set-jira-status.sh "$jira_key" "Resolved" || true
        fi
      else
        # fall back to a pull request from a branch prefixed by the Jira key
        if [[ -n "$jira_key" && "${HEAD_REF:-} ${PR_BODY:-}" == *"$jira_key"* ]]; then
          # that pull request gets its own ticket, not the one of the current pull request
          jira_key="$(HEAD_REF="" PR_BODY="" bash ../.github/scripts/translation/get-jira-key.sh centreon || true)"
          if [[ "$jira_key" =~ $jira_key_pattern ]]; then
            git commit --amend -m "chore(lang): update translations" -m "Refs: $jira_key"
          fi
        fi
        if [[ ! "$jira_key" =~ $jira_key_pattern ]] || ! bash ../.github/scripts/translation/create-translations-pr.sh "$jira_key"; then
          echo "::warning::Failed to push translations commit (no push access, e.g. fork pull request). Git patch will be available as artifact."
          cd ..
          git diff HEAD~1 > translations.patch
          TRANSLATIONS_PATCH_FILE="translations.patch"
        else
          bash ../.github/scripts/translation/set-jira-status.sh "$jira_key" "IN PROGRESS" "NEED REVIEW" || true
        fi
      fi
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
