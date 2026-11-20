#!/usr/bin/env bash
# Runs the "Check ACL computation on dataset S" CI step. See centreon/tests/acl/README.md, "In CI".
#
# Runs centAcl.php on dataset S, checks its rows and writes, and writes the time and memory of each run to the job
# summary. With BASE_REF set (the target branch of a pull request), the cron scripts of that branch are measured on the
# same runner: the step fails when this branch is more than 20 % slower, or uses more than 5 % more PHP memory, when
# little or nothing changed.
#
# Environment: BASE_REF (optional), RUNNER_TEMP, GITHUB_WORKSPACE.

# pipefail: a failed git archive must fail the step, not leave tar an empty archive.
set -euo pipefail

# qualify.php resolves its paths from the centreon directory.
cd "$(dirname "$0")/../../../centreon"

sudo install -d -o "${USER}" -g "$(id -gn)" /var/lib/centreon

if [[ -n "${BASE_REF:-}" ]]; then
  git fetch --no-tags --depth=1 origin "${BASE_REF}"
  baseline_dir="${RUNNER_TEMP}/acl-baseline-cron"
  mkdir -p "${baseline_dir}"
  # From the repository root: run from a subdirectory, git archive keeps only the paths under it.
  git -C "${GITHUB_WORKSPACE}" archive FETCH_HEAD:centreon/cron | tar -x -C "${baseline_dir}"
  php tests/acl/qualify.php scenarios S --baseline "${baseline_dir}" \
    --baseline-ref "${BASE_REF}" --baseline-commit "$(git rev-parse FETCH_HEAD)"
else
  php tests/acl/qualify.php scenarios S
fi
