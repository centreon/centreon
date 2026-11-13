#!/usr/bin/env bash
set -euo pipefail

# Move a Jira ticket through the given statuses, in order.
#
# Never fails: a warning is raised on stderr when a status cannot be reached.
#
# Usage: set-jira-status.sh <jira_key> <status>...
#
# Expected environment variables:
#   JIRA_BASE_URL, JIRA_USER_EMAIL, JIRA_API_TOKEN - Jira credentials (may be empty, e.g. fork pull request)

JIRA_KEY="${1:?Jira key is required}"
shift

warn() {
  echo "::warning::$*" >&2
}

jira_curl() {
  # credentials are given through a config file to keep them out of the process list
  curl --silent --show-error --fail --max-time 30 \
    --config <(printf 'user = "%s:%s"\n' "$JIRA_USER_EMAIL" "$JIRA_API_TOKEN") \
    --header "Accept: application/json" \
    "$@"
}

main() {
  local status response transition_id
  local url="${JIRA_BASE_URL:-}/rest/api/3/issue/${JIRA_KEY}/transitions"

  if [[ -z "${JIRA_BASE_URL:-}" || -z "${JIRA_USER_EMAIL:-}" || -z "${JIRA_API_TOKEN:-}" ]]; then
    warn "Jira credentials are not available. The status of ${JIRA_KEY} is left unchanged."
    return 0
  fi

  for status in "$@"; do
    transition_id=""
    if response="$(jira_curl --url "$url")"; then
      transition_id="$(RESPONSE="$response" STATUS="$status" php -r '
        foreach (json_decode((string) getenv("RESPONSE"), true)["transitions"] ?? [] as $transition) {
          if (strcasecmp($transition["to"]["name"] ?? "", (string) getenv("STATUS")) === 0) {
            echo $transition["id"];
            break;
          }
        }
      ')"
    fi

    if [[ -z "$transition_id" ]] || ! jira_curl --request POST --url "$url" \
      --header "Content-Type: application/json" \
      --data "{\"transition\": {\"id\": \"${transition_id}\"}}" > /dev/null; then
      warn "Failed to move Jira ticket ${JIRA_KEY} to status ${status}."
      return 0
    fi

    echo "Jira ticket ${JIRA_KEY} moved to status ${status}." >&2
  done
}

main "$@"
