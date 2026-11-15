#!/usr/bin/env bash
# Lint a SELinux policy module with selint; fail only on findings missing from
# the baseline (matched on file, check and message, not on line numbers).
set -euo pipefail

usage() {
  echo "usage: $0 [--update-baseline] <policy-dir> [config] [baseline]" >&2
  exit 2
}

update=0
if [[ "${1:-}" == "--update-baseline" ]]; then
  update=1
  shift
fi
policy_dir=${1:-}
[[ -n "$policy_dir" && -d "$policy_dir" ]] || usage
config=${2:-$policy_dir/selint.conf}
baseline=${3:-$policy_dir/selint-baseline.txt}

command -v selint >/dev/null || { echo "selint is not installed" >&2; exit 2; }
[[ -f "$config" ]] || { echo "missing config: $config" >&2; exit 2; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

shopt -s nullglob
sources=("$policy_dir"/*.te "$policy_dir"/*.if "$policy_dir"/*.fc)
(( ${#sources[@]} > 0 )) || { echo "no policy sources in $policy_dir" >&2; exit 2; }
cp "${sources[@]}" "$work/"
# policy_module() must have a numeric version, packaging substitutes it the same way.
te_files=("$work"/*.te)
(( ${#te_files[@]} == 0 )) || sed -i 's/@VERSION@/0.0/g' "${te_files[@]}"
files=("$work"/*.te "$work"/*.if "$work"/*.fc)

rc=0
selint --config="$config" --color=off --level=C --summary "${files[@]}" >"$work/raw.txt" 2>&1 || rc=$?
cat "$work/raw.txt"
if (( rc != 0 )); then
  echo "selint failed with exit code $rc (parse error?)" >&2
  exit "$rc"
fi

# "file:  12: (W): message (W-004)" -> "file: W-004: message"
sed -nE 's/^([^:]+):[[:space:]]*[0-9]+: \([CSWEF]\): (.*) \(([CSWEF]-[0-9]+)\)$/\1: \3: \2/p' \
  "$work/raw.txt" | LC_ALL=C sort >"$work/current.txt"

if (( update )); then
  {
    echo "# selint findings accepted as-is. Regenerate with:"
    echo "#   .github/scripts/selinux-lint.sh --update-baseline $policy_dir"
    cat "$work/current.txt"
  } >"$baseline"
  echo "Baseline written to $baseline ($(wc -l <"$work/current.txt") findings)"
  exit 0
fi

touch "$work/baseline.txt"
[[ ! -f "$baseline" ]] || grep -v -e '^#' -e '^[[:space:]]*$' "$baseline" | LC_ALL=C sort >"$work/baseline.txt" || true

new=$(LC_ALL=C comm -23 "$work/current.txt" "$work/baseline.txt")
fixed=$(LC_ALL=C comm -13 "$work/current.txt" "$work/baseline.txt")

echo
echo "selint: $(wc -l <"$work/current.txt") finding(s), $(wc -l <"$work/baseline.txt") in baseline"
if [[ -n "$fixed" ]]; then
  echo "Baseline entries no longer reported (run with --update-baseline to drop them):"
  printf '  %s\n' "$fixed"
fi
if [[ -n "$new" ]]; then
  echo "New selint finding(s) not in $baseline:"
  while IFS= read -r line; do
    echo "  $line"
    [[ -z "${GITHUB_ACTIONS:-}" ]] || echo "::error title=selint::$line"
  done <<<"$new"
  exit 1
fi
echo "No new selint finding."
