#!/usr/bin/env sh
set -eu

# Scan only the commits being pushed for secrets.
#
# Two traps this avoids on purpose:
#   - --no-git scans the whole working tree, including the multi-GB data/
#     and logs/ dirs git ignores, which hangs every push;
#   - scanning the full history keeps re-flagging the dev-image test
#     credentials committed long ago, which blocks every push.
# git's pre-push contract feeds "<localref> <localsha> <remoteref>
# <remotesha>" lines on stdin; we scan just the new range each carries.

is_zero() {
  case "$1" in
    *[!0]*) return 1 ;;
    *) return 0 ;;
  esac
}

status=0
while read -r _local_ref local_sha _remote_ref remote_sha; do
  # Branch deletion: nothing to scan.
  if is_zero "$local_sha"; then
    continue
  fi

  if is_zero "$remote_sha"; then
    # New branch: scan commits not yet present on any remote.
    log_opts="$local_sha --not --remotes"
  else
    # Existing branch: scan only the newly pushed range.
    log_opts="$remote_sha..$local_sha"
  fi

  if ! gitleaks detect \
    --log-opts="$log_opts" \
    --exit-code=2 \
    --verbose \
    --no-banner; then
    status=1
  fi
done

exit "$status"
