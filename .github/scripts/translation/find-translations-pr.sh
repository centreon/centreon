#!/usr/bin/env bash
set -euo pipefail

# Print the URL of the translation pull request already open for a branch, if any.
#
# Such a pull request is opened by create-translations-pr.sh when the translation commit cannot be
# pushed. Prints nothing when there is none or when it cannot be checked.
#
# Expected environment variables:
#   GH_TOKEN           - token allowed to list pull requests (may be empty, e.g. fork pull request)
#   BRANCH_NAME        - branch targeted by the translation pull request
#   GITHUB_REPOSITORY  - owner/name of the repository
#   GITHUB_API_URL     - GitHub API URL

BRANCH_NAME="${BRANCH_NAME:-}"
GH_TOKEN="${GH_TOKEN:-}"

main() {
  local response

  if [[ -z "$GH_TOKEN" || -z "$BRANCH_NAME" ]]; then
    return 0
  fi

  # the token is given through a config file to keep it out of the process list
  if ! response="$(curl --silent --show-error --fail --max-time 30 \
    --config <(printf 'header = "Authorization: Bearer %s"\n' "$GH_TOKEN") \
    --header "Accept: application/vnd.github+json" \
    --get --url "${GITHUB_API_URL}/repos/${GITHUB_REPOSITORY}/pulls" \
    --data-urlencode "state=open" --data-urlencode "base=${BRANCH_NAME}" --data-urlencode "per_page=100")"; then
    echo "::warning::Failed to list the open translation pull requests." >&2
    return 0
  fi

  RESPONSE="$response" REPOSITORY="$GITHUB_REPOSITORY" php -r '
    foreach (json_decode((string) getenv("RESPONSE"), true) ?: [] as $pull) {
      if (
        ($pull["head"]["repo"]["full_name"] ?? "") === getenv("REPOSITORY")
        && preg_match("/^[A-Z][A-Z0-9_]+-[0-9]+-update-translations$/", $pull["head"]["ref"] ?? "")
      ) {
        echo $pull["html_url"];
        break;
      }
    }
  '
}

main
