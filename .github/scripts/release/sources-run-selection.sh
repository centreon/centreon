#!/usr/bin/env bash

# Finds the deliver-sources job a component tag started, for sources-release-entries.sh.
# Kept apart from that script so the selection can be tested without the network:
# sources-run-selection-test.sh feeds it fixture runs and stubs gh.

# Push runs a tag started here, newest first, as "id<TAB>status" lines. The repository check stops
# a fork pull request whose branch is named like the tag from impersonating it; -s merges the
# pages --paginate emits, so runs of different pages are sorted together.
tag_push_runs() {
  local runs_json="$1" tag="$2" repo="$3"
  jq -s -r --arg tag "$tag" --arg repo "$repo" \
    '[.[].workflow_runs[]? | select(.head_branch == $tag and .event == "push"
                                    and .head_repository.full_name == $repo)]
     | sort_by(.created_at, .id) | reverse | .[] | [(.id|tostring), .status] | @tsv' \
    "$runs_json"
}

# The deliver-sources job of one run as "status<TAB>conclusion<TAB>url", empty when it has none.
# Fails when the jobs cannot be read, which must not pass for a run without the job.
run_deliver_sources_job() {
  local repo="$1" run_id="$2" jobs
  jobs="$(gh api --paginate "repos/$repo/actions/runs/$run_id/jobs?per_page=100" 2>/dev/null)" \
    || return 1
  jq -s -r '[.[].jobs[]? | select(.name == "deliver-sources")] | last
            | select(.) | [.status, (.conclusion // ""), .html_url] | @tsv' <<<"$jobs"
}

# Prints "job<TAB>status<TAB>conclusion<TAB>url" for the newest run of the tag that has the job.
# Otherwise "none" (no run yet), "pending" (a run still going may add the job, or the jobs could
# not be read) or "absent" (every run finished without it).
tag_deliver_sources_state() {
  local runs_json="$1" tag="$2" repo="$3"
  local -a runs=()
  local line run_id run_status job running="false"

  # one tag can start several workflows (helm and web, say) in the same second, and only one of
  # them delivers sources, so every run is looked at rather than just the newest
  mapfile -t runs < <(tag_push_runs "$runs_json" "$tag" "$repo" 2>/dev/null || true)
  (( ${#runs[@]} > 0 )) || { echo "none"; return 0; }

  for line in "${runs[@]}"; do
    run_id="${line%%$'\t'*}"
    run_status="${line#*$'\t'}"
    # falling through to an older run here could settle on a stale attempt for good; retry instead
    if ! job="$(run_deliver_sources_job "$repo" "$run_id")"; then
      echo "pending"
      return 0
    fi
    if [[ -n "$job" ]]; then
      printf 'job\t%s\n' "$job"
      return 0
    fi
    [[ "$run_status" == "completed" ]] || running="true"
  done

  if [[ "$running" == "true" ]]; then echo "pending"; else echo "absent"; fi
}
