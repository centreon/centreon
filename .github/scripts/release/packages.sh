#!/usr/bin/env bash
# packages.sh
# Inspect packages.centreon.com and report, per selected component, the
# package count of the last 2 published versions (per OS). If the two
# counts differ, list exactly which package(s) are missing or new.
#
# Usage (interactive): ./packages.sh
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env-test"
if [[ -f "$ENV_FILE" ]]; then
  set -a; source "$ENV_FILE"; set +a
fi

STD_BASE="https://packages.centreon.com/rpm-standard"
BIZ_TOKEN="${RPM_BUSINESS_TOKEN:-1a97ff9985262bf3daf7a0919f9c59a6}"
BIZ_BASE="https://packages.centreon.com/rpm-business/${BIZ_TOKEN}"

AVAILABLE_TRAINS=("24.10" "25.10" "26.10")

oses_for_train() {
  # 26.10 is the first train built for el10, in addition to el8/el9.
  local train="$1"
  if [[ "$train" == "26.10" ]]; then
    echo "el8 el9 el10"
  else
    echo "el8 el9"
  fi
}

WHITE=$'\033[0m'
RED=$'\033[31m'
GREEN=$'\033[32m'
YELLOW=$'\033[33m'
BOLD=$'\033[1m'
RESET=$'\033[0m'

MW_URL_PROD="${MW_URL_PROD:-https://api.imp.centreon.com/api}"
MW_URL_PPD="${MW_URL_PPD:-https://ppd-api.imp.centreon.com/api}"

# name|group|arch|base   (base: STD or BIZ)
ORDERED_COMPONENTS=(
  "awie|oss|noarch|STD"
  "dsm|oss|noarch|STD"
  "ha|oss|noarch|STD"
  "open-tickets|oss|noarch|STD"
  "web|oss|noarch|STD"
  "anomaly-detection|modules|noarch|STD"
  "autodisco|modules|noarch|STD"
  "bam|modules|noarch|BIZ"
  "it-edition-extensions|modules|noarch|STD"
  "lm|modules|noarch|STD"
  "map|modules|noarch|BIZ"
  "mbi|modules|noarch|BIZ"
  "ppm|modules|noarch|STD"
  "collect|collect|x86_64|STD"
  "gorgone|collect|noarch|STD"
  "monitoring-agent|collect|x86_64|STD"
)

RPM_PLUGINS_BASE="https://packages.centreon.com/rpm-plugins"
APT_PLUGINS_TESTING_BASE="https://packages.centreon.com/apt-plugins-testing"
APT_PLUGINS_STABLE_BASE="https://packages.centreon.com/apt-plugins-stable"
UBUNTU_PLUGINS_TESTING_BASE="https://packages.centreon.com/ubuntu-plugins-testing"
UBUNTU_PLUGINS_STABLE_BASE="https://packages.centreon.com/ubuntu-plugins-stable"
RPM_CONNECTORS_BASE="https://packages.centreon.com/rpm-connectors/2e83f5ff110c44a9cab8f8c7ebbe3c4f"
APT_CONNECTORS_TESTING_BASE="https://packages.centreon.com/apt-connectors-testing"
APT_CONNECTORS_STABLE_BASE="https://packages.centreon.com/apt-connectors-stable"
STREAM_CONNECTORS_RPM_OSES=("el8" "el9" "el10")
STREAM_CONNECTORS_DEB_DISTROS=("bookworm" "bullseye" "trixie")
CENTREON_PLUGINS_UBUNTU_SUFFIXES=("0ubuntu.22.04" "0ubuntu.24.04")

# ---------------------------------------------------------------
# Stream connectors release check
# ---------------------------------------------------------------

report_stream_connectors_diff() {
  # $1 = platform label (os/distro), $2 = stage, $3 = date, $4 = expected array name, $5 = found array name
  local platform="$1" stage="$2" date="$3"
  local -n sc_expected_ref=$4
  local -n sc_found_ref=$5

  local expected_file found_file
  expected_file=$(mktemp); found_file=$(mktemp)
  printf '%s\n' "${sc_expected_ref[@]}" | sort -u > "$expected_file"
  printf '%s\n' "${sc_found_ref[@]}" | sort -u > "$found_file"
  local missing new
  missing=$(comm -23 "$expected_file" "$found_file")
  new=$(comm -13 "$expected_file" "$found_file")
  rm -f "$expected_file" "$found_file"

  if [[ -z "$missing" && -z "$new" && ${#sc_found_ref[@]} -gt 0 ]]; then
    printf "    ${YELLOW}%s${RESET}: OK — %d/%d packages (%s)\n" \
      "$platform" "${#sc_found_ref[@]}" "${#sc_expected_ref[@]}" "$date"
  else
    printf "    ${RED}${BOLD}%s${RESET}: %d/%d packages (%s)\n" \
      "$platform" "${#sc_found_ref[@]}" "${#sc_expected_ref[@]}" "$date"
    [[ -n "$missing" ]] && while IFS= read -r c; do
      [[ -n "$c" ]] && echo "      ${RED}- missing: $c${RESET}"
    done <<< "$missing"
    [[ -n "$new" ]] && while IFS= read -r c; do
      [[ -n "$c" ]] && echo "      ${YELLOW}- unexpected: $c${RESET}"
    done <<< "$new"
  fi
  return 0
}

check_stream_connectors_rpm() {
  # $1 = os, $2 = stage (testing|stable), $3 = release date (YYYYMMDD), $4 = expected array name
  local os="$1" stage="$2" release_date="$3"
  local seg=""
  [[ "$stage" == "stable" ]] && seg="RPMS/"
  local url="${RPM_PLUGINS_BASE}/${os}/${stage}/noarch/${seg}stream-connectors/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${os}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  local target_date="$release_date"
  if [[ "$stage" == "stable" ]]; then
    target_date=$(echo "$html" | grep -oE "centreon-stream-connector-[a-z0-9]+-[0-9]{8}-[0-9]+\.${os}\.noarch\.rpm" \
      | grep -oE '[0-9]{8}' | sort -u | tail -1 || true)
    if [[ -z "$target_date" ]]; then
      echo "    ${YELLOW}${os}: no packages found${RESET}"
      return
    fi
  fi

  mapfile -t found < <(echo "$html" \
    | grep -oE "centreon-stream-connector-[a-z0-9]+-${target_date}-[0-9]+\.${os}\.noarch\.rpm" \
    | sed -E "s/-${target_date}-[0-9]+\.${os}\.noarch\.rpm\$//; s/^centreon-stream-connector-//" \
    | sort -u)
  report_stream_connectors_diff "$os" "$stage" "$target_date" "$4" found
}

check_stream_connectors_deb() {
  # $1 = distro, $2 = stage (testing|stable), $3 = release date (YYYYMMDD), $4 = expected array name
  local distro="$1" stage="$2" release_date="$3"
  local base="$APT_PLUGINS_TESTING_BASE"
  [[ "$stage" == "stable" ]] && base="$APT_PLUGINS_STABLE_BASE"
  local url="${base}/pool/stream-connectors/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${distro}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  local target_date="$release_date"
  if [[ "$stage" == "stable" ]]; then
    target_date=$(echo "$html" | grep -oE "centreon-stream-connector-[a-z0-9]+_[0-9]{8}-[0-9]+%7E${distro}_all\.deb" \
      | grep -oE '[0-9]{8}' | sort -u | tail -1 || true)
    if [[ -z "$target_date" ]]; then
      echo "    ${YELLOW}${distro}: no packages found${RESET}"
      return
    fi
  fi

  mapfile -t found < <(echo "$html" \
    | grep -oE "centreon-stream-connector-[a-z0-9]+_${target_date}-[0-9]+%7E${distro}_all\.deb" \
    | sed -E "s/_${target_date}-[0-9]+%7E${distro}_all\.deb\$//; s/^centreon-stream-connector-//" \
    | sort -u)
  report_stream_connectors_diff "$distro" "$stage" "$target_date" "$4" found
}

run_stream_connectors_check() {
  echo ""
  read -rp "Release PR number (centreon-stream-connector-scripts): " pr_number || true
  pr_number="${pr_number//$'\r'/}"
  if [[ -z "$pr_number" ]]; then
    echo "No PR number provided. Exiting."
    return
  fi

  local pr_json
  pr_json=$(gh pr view "$pr_number" --repo centreon/centreon-stream-connector-scripts \
    --json title,mergeCommit,headRefOid 2>/dev/null || true)
  if [[ -z "$pr_json" ]]; then
    echo "${RED}Could not fetch PR #${pr_number}.${RESET}"
    return
  fi

  local title merge_sha head_sha ref release_date
  title=$(echo "$pr_json" | jq -r '.title')
  merge_sha=$(echo "$pr_json" | jq -r '.mergeCommit.oid // empty')
  head_sha=$(echo "$pr_json" | jq -r '.headRefOid')
  ref="${merge_sha:-$head_sha}"

  if [[ "$title" =~ ([0-9]{8}) ]]; then
    release_date="${BASH_REMATCH[1]}"
  else
    read -rp "Could not detect release date from PR title '${title}'. Enter it (YYYYMMDD): " release_date || true
    release_date="${release_date//$'\r'/}"
  fi

  echo ""
  echo "PR #${pr_number}: ${title} (release date: ${release_date})"
  echo ""

  mapfile -t expected < <(gh api "repos/centreon/centreon-stream-connector-scripts/contents/centreon-certified?ref=${ref}" \
    --jq '.[].name' 2>/dev/null | sort -u)
  if [[ ${#expected[@]} -eq 0 ]]; then
    echo "${RED}Could not list expected connectors from centreon-certified/ at ${ref}.${RESET}"
    return
  fi
  echo "Expected connectors (${#expected[@]}): ${expected[*]}"
  echo ""

  echo "-- RPM (rpm-plugins) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for os in "${STREAM_CONNECTORS_RPM_OSES[@]}"; do
      check_stream_connectors_rpm "$os" "$stage" "$release_date" expected
    done
  done

  echo ""
  echo "-- DEB (apt-plugins) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for distro in "${STREAM_CONNECTORS_DEB_DISTROS[@]}"; do
      check_stream_connectors_deb "$distro" "$stage" "$release_date" expected
    done
  done
}

# ---------------------------------------------------------------
# centreon-plugins release check
# ---------------------------------------------------------------

CENTREON_PLUGINS_DEB_SUFFIXES=("deb11u1" "deb12u1" "deb13u1")

check_plugins_rpm() {
  # $1 = os, $2 = stage (testing|stable), $3 = release date (YYYYMM00), $4 = expected array name
  local os="$1" stage="$2" release_date="$3"
  local seg=""
  [[ "$stage" == "stable" ]] && seg="RPMS/"
  local url="${RPM_PLUGINS_BASE}/${os}/${stage}/noarch/${seg}plugins/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${os}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  local target_date="$release_date"
  if [[ "$stage" == "stable" ]]; then
    target_date=$(echo "$html" | grep -oE "centreon-plugin-[A-Za-z0-9-]+-[0-9]{8}-[0-9]+\.${os}\.noarch\.rpm" \
      | grep -oE '[0-9]{8}' | sort -u | tail -1 || true)
    if [[ -z "$target_date" ]]; then
      echo "    ${YELLOW}${os}: no packages found${RESET}"
      return
    fi
  fi

  mapfile -t found < <(echo "$html" \
    | grep -oE "centreon-plugin-[A-Za-z0-9-]+-${target_date}-[0-9]+\.${os}\.noarch\.rpm" \
    | sed -E "s/-${target_date}-[0-9]+\.${os}\.noarch\.rpm\$//" \
    | sort -u)
  report_stream_connectors_diff "$os" "$stage" "$target_date" "$4" found
}

check_plugins_deb() {
  # $1 = distro suffix (deb11u1|deb12u1|deb13u1), $2 = stage, $3 = release date, $4 = expected array name
  local distro_suffix="$1" stage="$2" release_date="$3"
  local -n plg_expected_ref=$4
  local base="$APT_PLUGINS_TESTING_BASE"
  [[ "$stage" == "stable" ]] && base="$APT_PLUGINS_STABLE_BASE"
  local url="${base}/pool/plugins/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${distro_suffix}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  local target_date="$release_date"
  if [[ "$stage" == "stable" ]]; then
    target_date=$(echo "$html" | grep -oE "centreon-plugin-[a-z0-9-]+_[0-9]{8}-[0-9]+\+${distro_suffix}_all\.deb" \
      | grep -oE '[0-9]{8}' | sort -u | tail -1 || true)
    if [[ -z "$target_date" ]]; then
      echo "    ${YELLOW}${distro_suffix}: no packages found${RESET}"
      return
    fi
  fi

  mapfile -t found < <(echo "$html" \
    | grep -oE "centreon-plugin-[a-z0-9-]+_${target_date}-[0-9]+\+${distro_suffix}_all\.deb" \
    | sed -E "s/_${target_date}-[0-9]+\+${distro_suffix}_all\.deb\$//" \
    | sort -u)

  # Debian packages are always lowercased, but the packaging/ directory names
  # (the expected list) preserve the mixed case used in the RPM names.
  local -a expected_lc=()
  local e
  for e in "${plg_expected_ref[@]}"; do
    expected_lc+=("${e,,}")
  done

  report_stream_connectors_diff "$distro_suffix" "$stage" "$target_date" expected_lc found
}

check_plugins_ubuntu() {
  # $1 = ubuntu suffix (0ubuntu.22.04|0ubuntu.24.04), $2 = stage, $3 = release date, $4 = expected array name
  local suffix="$1" stage="$2" release_date="$3"
  local -n plg_expected_ref=$4
  local base="$UBUNTU_PLUGINS_TESTING_BASE"
  [[ "$stage" == "stable" ]] && base="$UBUNTU_PLUGINS_STABLE_BASE"
  local url="${base}/pool/plugins/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${suffix}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  local suffix_re="${suffix//./\\.}"
  local target_date="$release_date"
  if [[ "$stage" == "stable" ]]; then
    target_date=$(echo "$html" | grep -oE "centreon-plugin-[a-z0-9-]+_[0-9]{8}-[0-9]+-${suffix_re}_all\.deb" \
      | grep -oE '[0-9]{8}' | sort -u | tail -1 || true)
    if [[ -z "$target_date" ]]; then
      echo "    ${YELLOW}${suffix}: no packages found${RESET}"
      return
    fi
  fi

  mapfile -t found < <(echo "$html" \
    | grep -oE "centreon-plugin-[a-z0-9-]+_${target_date}-[0-9]+-${suffix_re}_all\.deb" \
    | sed -E "s/_${target_date}-[0-9]+-${suffix_re}_all\.deb\$//" \
    | sort -u)

  # Ubuntu packages are always lowercased, same as Debian ones.
  local -a expected_lc=()
  local e
  for e in "${plg_expected_ref[@]}"; do
    expected_lc+=("${e,,}")
  done

  report_stream_connectors_diff "$suffix" "$stage" "$target_date" expected_lc found
}

run_plugins_check() {
  echo ""
  read -rp "Release PR number (centreon-plugins): " pr_number || true
  pr_number="${pr_number//$'\r'/}"
  if [[ -z "$pr_number" ]]; then
    echo "No PR number provided. Exiting."
    return
  fi

  local pr_json
  pr_json=$(gh pr view "$pr_number" --repo centreon/centreon-plugins \
    --json title,mergeCommit,headRefOid 2>/dev/null || true)
  if [[ -z "$pr_json" ]]; then
    echo "${RED}Could not fetch PR #${pr_number}.${RESET}"
    return
  fi

  local title merge_sha head_sha ref release_date
  title=$(echo "$pr_json" | jq -r '.title')
  merge_sha=$(echo "$pr_json" | jq -r '.mergeCommit.oid // empty')
  head_sha=$(echo "$pr_json" | jq -r '.headRefOid')
  ref="${merge_sha:-$head_sha}"

  if [[ "$title" =~ ([0-9]{8}) ]]; then
    release_date="${BASH_REMATCH[1]}"
  else
    read -rp "Could not detect release date from PR title '${title}'. Enter it (YYYYMMDD): " release_date || true
    release_date="${release_date//$'\r'/}"
  fi

  echo ""
  echo "PR #${pr_number}: ${title} (release date: ${release_date})"
  echo ""

  mapfile -t plugins_expected < <(gh api "repos/centreon/centreon-plugins/contents/packaging?ref=${ref}" \
    --jq '.[].name' 2>/dev/null | sort -u)
  if [[ ${#plugins_expected[@]} -eq 0 ]]; then
    echo "${RED}Could not list expected packages from packaging/ at ${ref}.${RESET}"
    return
  fi
  echo "Expected packages: ${#plugins_expected[@]}"
  echo ""

  echo "-- RPM (rpm-plugins) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for os in "${STREAM_CONNECTORS_RPM_OSES[@]}"; do
      check_plugins_rpm "$os" "$stage" "$release_date" plugins_expected
    done
  done

  echo ""
  echo "-- DEB (apt-plugins) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for suffix in "${CENTREON_PLUGINS_DEB_SUFFIXES[@]}"; do
      check_plugins_deb "$suffix" "$stage" "$release_date" plugins_expected
    done
  done

  echo ""
  echo "-- Ubuntu (ubuntu-plugins) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for suffix in "${CENTREON_PLUGINS_UBUNTU_SUFFIXES[@]}"; do
      check_plugins_ubuntu "$suffix" "$stage" "$release_date" plugins_expected
    done
  done
}

# ---------------------------------------------------------------
# centreon-plugin-packs release check
# ---------------------------------------------------------------
# Unlike centreon-plugins, this repo does NOT rebuild its whole catalog every
# release — each pack advances independently (its own RPM patch number, its
# own DEB build date), so there is no single version/date string shared by
# every package in a release. "Expected" is therefore derived from the packs
# actually touched by the PR (src/<pack>/...), and presence is checked by
# matching the calendar month of the release rather than an exact version.

report_packs_diff() {
  # $1 = platform label, $2 = stage, $3 = month label (e.g. Jul-2026),
  # $4 = expected array name, $5 = found array name
  local platform="$1" stage="$2" month_label="$3"
  local -n pk_expected_ref=$4
  local -n pk_found_ref=$5

  local expected_file found_file
  expected_file=$(mktemp); found_file=$(mktemp)
  printf '%s\n' "${pk_expected_ref[@]}" | sort -u > "$expected_file"
  printf '%s\n' "${pk_found_ref[@]}" | sort -u > "$found_file"
  local missing
  missing=$(comm -23 "$expected_file" "$found_file")
  rm -f "$expected_file" "$found_file"

  local missing_count=0
  [[ -n "$missing" ]] && missing_count=$(echo "$missing" | grep -c .)
  local present_count=$(( ${#pk_expected_ref[@]} - missing_count ))

  if [[ "$missing_count" -eq 0 ]]; then
    printf "    ${YELLOW}%s${RESET}: OK — %d/%d packs present (%s)\n" \
      "$platform" "$present_count" "${#pk_expected_ref[@]}" "$month_label"
  else
    printf "    ${RED}${BOLD}%s${RESET}: %d/%d packs present (%s)\n" \
      "$platform" "$present_count" "${#pk_expected_ref[@]}" "$month_label"
    [[ -n "$missing" ]] && while IFS= read -r c; do
      [[ -n "$c" ]] && echo "      ${RED}- missing: $c${RESET}"
    done <<< "$missing"
  fi
  return 0
}

check_packs_rpm() {
  # $1 = os, $2 = stage, $3 = month label (e.g. Jul-2026), $4 = expected array name
  local os="$1" stage="$2" month_label="$3"
  local seg=""
  [[ "$stage" == "stable" ]] && seg="RPMS/"
  local url="${RPM_CONNECTORS_BASE}/${os}/${stage}/noarch/${seg}connectors/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${os}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  mapfile -t found < <(echo "$html" \
    | grep -oE "href=\"[^\"]+\.rpm\"[^<]*</a>[[:space:]]+[0-9]{2}-${month_label}" \
    | grep -oE 'href="[^"]+\.rpm"' \
    | sed -E "s/href=\"//; s/\"\$//; s/-[0-9]+\.[0-9]+\.[0-9]+-[0-9]+\.${os}\.noarch\.rpm\$//; s/^centreon-pack-//" \
    | sort -u)
  report_packs_diff "$os" "$stage" "$month_label" "$4" found
}

check_packs_deb() {
  # $1 = distro suffix (deb11u1|deb12u1|deb13u1), $2 = stage, $3 = month label, $4 = expected array name
  local distro_suffix="$1" stage="$2" month_label="$3"
  local base="$APT_CONNECTORS_TESTING_BASE"
  [[ "$stage" == "stable" ]] && base="$APT_CONNECTORS_STABLE_BASE"
  local url="${base}/pool/connectors/"

  local html
  html=$(curl -s -f "$url" 2>/dev/null || true)
  if [[ -z "$html" ]]; then
    echo "    ${YELLOW}${distro_suffix}: could not fetch (404 or empty) — $url${RESET}"
    return
  fi

  # Version format is inconsistent across distros for this repo: some builds
  # use a plain date (20260721), others a dotted semver (26.07.0) — allow both.
  mapfile -t found < <(echo "$html" \
    | grep -oE "href=\"[^\"]+\+${distro_suffix}_all\.deb\"[^<]*</a>[[:space:]]+[0-9]{2}-${month_label}" \
    | grep -oE 'href="[^"]+\.deb"' \
    | sed -E "s/href=\"//; s/\"\$//; s/_[0-9.]+-[0-9]+\+${distro_suffix}_all\.deb\$//; s/^centreon-pack-//" \
    | sort -u)
  report_packs_diff "$distro_suffix" "$stage" "$month_label" "$4" found
}

run_packs_check() {
  echo ""
  read -rp "Release PR number (centreon-plugin-packs): " pr_number || true
  pr_number="${pr_number//$'\r'/}"
  if [[ -z "$pr_number" ]]; then
    echo "No PR number provided. Exiting."
    return
  fi

  local pr_json
  pr_json=$(gh pr view "$pr_number" --repo centreon/centreon-plugin-packs \
    --json title,mergeCommit,headRefOid,files 2>/dev/null || true)
  if [[ -z "$pr_json" ]]; then
    echo "${RED}Could not fetch PR #${pr_number}.${RESET}"
    return
  fi

  local title merge_sha head_sha ref month_label
  title=$(echo "$pr_json" | jq -r '.title')
  merge_sha=$(echo "$pr_json" | jq -r '.mergeCommit.oid // empty')
  head_sha=$(echo "$pr_json" | jq -r '.headRefOid')
  ref="${merge_sha:-$head_sha}"

  if [[ "$title" =~ ([0-9]{4})([0-9]{2})[0-9]{2} ]]; then
    month_label=$(date -d "${BASH_REMATCH[1]}-${BASH_REMATCH[2]}-01" +%b-%Y)
  else
    read -rp "Could not detect release month from PR title '${title}'. Enter it (e.g. Jul-2026): " month_label || true
    month_label="${month_label//$'\r'/}"
  fi

  echo ""
  echo "PR #${pr_number}: ${title} (release month: ${month_label})"
  echo ""

  # Expected = packs actually touched by this PR — this repo does partial,
  # per-pack releases, not a full-catalog rebuild every cycle.
  mapfile -t packs_expected < <(echo "$pr_json" | jq -r '.files[].path' \
    | grep -oE '^src/[^/]+' | sed 's|^src/||' | sort -u)
  if [[ ${#packs_expected[@]} -eq 0 ]]; then
    echo "${RED}Could not find any src/<pack> directories touched by this PR.${RESET}"
    return
  fi
  echo "Expected packs (touched by this PR): ${#packs_expected[@]}"
  echo ""

  echo "-- RPM (rpm-connectors) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for os in "${STREAM_CONNECTORS_RPM_OSES[@]}"; do
      check_packs_rpm "$os" "$stage" "$month_label" packs_expected
    done
  done

  echo ""
  echo "-- DEB (apt-connectors) --"
  for stage in testing stable; do
    echo "  ${BOLD}${stage}${RESET}:"
    for suffix in "${CENTREON_PLUGINS_DEB_SUFFIXES[@]}"; do
      check_packs_deb "$suffix" "$stage" "$month_label" packs_expected
    done
  done
}

# ---------------------------------------------------------------
# Middleware check (ported from middleware.sh)
# Compares plugin-pack slugs from a centreon-plugin-packs PR against the
# preprod and prod middleware/IMP API, rather than the package repos.
# ---------------------------------------------------------------

mw_version() {
  local base_url="$1" slug="$2"
  curl -sf -G \
    -H 'Content-Type: application/json' \
    --data-urlencode "filter[slug]=${slug}" \
    "${base_url}/pluginpack/pluginpack" 2>/dev/null \
    | jq -r 'if (.data | length) > 0 then .data[0].attributes.version else "--" end' \
    2>/dev/null || echo "error"
}

pack_version() {
  local slug="$1" ref="$2"
  gh api "repos/centreon/centreon-plugin-packs/contents/src/${slug}/pack.json?ref=${ref}" \
    --jq '.content' 2>/dev/null \
    | base64 -d 2>/dev/null \
    | jq -r '.version // .information.version // "unknown"' 2>/dev/null \
    || echo "unknown"
}

run_middleware_check() {
  local pr_number
  echo ""
  read -rp "PR number (centreon/centreon-plugin-packs): " pr_number || true
  pr_number="${pr_number//$'\r'/}"
  if [[ -z "$pr_number" ]]; then
    echo "No PR number provided. Exiting."
    return
  fi

  echo ""
  echo "Fetching PR #${pr_number}..."

  local pr_json pr_title pr_state pr_ref pr_merged
  pr_json=$(gh pr view "$pr_number" --repo centreon/centreon-plugin-packs --json title,state,headRefName,files)
  pr_title=$(echo "$pr_json" | jq -r '.title')
  pr_state=$(echo "$pr_json" | jq -r '.state')
  pr_ref=$(echo "$pr_json"   | jq -r '.headRefName')
  pr_merged=$([[ "$pr_state" == "MERGED" ]] && echo "true" || echo "false")

  # Title contains something like "release-20260400" → 26.04
  local release_code yy mm expected_version
  release_code=$(echo "$pr_title" | grep -oP '\d{8}' | head -1)
  if [[ -n "$release_code" ]]; then
    yy="${release_code:2:2}"
    mm="${release_code:4:2}"
    expected_version="${yy}.${mm}"
  else
    echo "Warning: could not parse release version from PR title: $pr_title"
    expected_version="unknown"
  fi

  # Extract slugs and detect new packs (additions only, no deletions)
  local slugs new_slugs
  slugs=$(echo "$pr_json" | jq -r '.files[].path' \
    | grep '^src/' \
    | sed 's|src/||; s|/.*||' \
    | sort -u)

  new_slugs=$(echo "$pr_json" | jq -r '
    .files[]
    | select(.path | startswith("src/"))
    | {slug: (.path | split("/")[1]), deletions: .deletions}
  ' | jq -s '
    group_by(.slug)
    | map({slug: .[0].slug, deletions: map(.deletions) | add})
    | map(select(.deletions == 0))
    | .[].slug
  ' | tr -d '"')

  if [[ -z "$slugs" ]]; then
    echo "No slugs found under src/ in PR #${pr_number}."
    return
  fi

  local slug_count
  slug_count=$(echo "$slugs" | wc -l | tr -d ' ')

  echo "Title   : ${pr_title}"
  echo "Version : ${expected_version}"
  echo "State   : ${pr_state}"
  echo "Branch  : ${pr_ref}"
  echo "Slugs   : ${slug_count}"
  echo ""

  if [[ "$pr_merged" == "true" ]]; then
    echo "PR is merged — expecting ${expected_version}.x on both preprod AND prod."
  else
    echo "PR is not merged — expecting ${expected_version}.x on preprod only."
  fi
  echo ""

  local sep
  sep=$(printf '%0.s-' {1..110})

  printf '%-50s  %-12s  %-13s  %-13s  %s\n' "SLUG" "PR VERSION" "PREPROD" "PROD" "STATUS"
  echo "$sep"

  local ok=0 ok_new=0 missing_ppd=0 missing_prod=0 missing_both=0 wrong_version=0

  while IFS= read -r slug; do
    local pr_v ppd_v prod_v ppd_ok prod_ok is_new status color
    pr_v=$(pack_version "$slug" "$pr_ref")
    ppd_v=$(mw_version "$MW_URL_PPD" "$slug")
    prod_v=$(mw_version "$MW_URL_PROD" "$slug")

    ppd_ok=false
    prod_ok=false
    is_new=false
    [[ "$ppd_v"  != "--" && "$ppd_v"  == "${expected_version}"* ]] && ppd_ok=true
    [[ "$prod_v" != "--" && "$prod_v" == "${expected_version}"* ]] && prod_ok=true
    echo "$new_slugs" | grep -qx "$slug" && is_new=true

    if [[ "$pr_merged" == "true" ]]; then
      if $ppd_ok && $prod_ok; then
        status="OK";           ((ok++))            || true
      elif [[ "$ppd_v" == "--" && "$prod_v" == "--" ]]; then
        status="MISSING BOTH"; ((missing_both++))  || true
      elif [[ "$ppd_v" == "--" ]]; then
        status="MISSING PPD";  ((missing_ppd++))   || true
      elif [[ "$prod_v" == "--" ]]; then
        status="MISSING PROD"; ((missing_prod++))  || true
      else
        status="WRONG VERSION"; ((wrong_version++)) || true
      fi
    else
      if $ppd_ok; then
        if [[ "$prod_v" == "--" ]] && $is_new; then
          status="OK (new pack)"; ((ok_new++))      || true
        else
          status="OK";            ((ok++))            || true
        fi
      elif [[ "$ppd_v" == "--" ]]; then
        status="MISSING PPD";  ((missing_ppd++))   || true
      else
        status="WRONG VERSION"; ((wrong_version++)) || true
      fi
    fi

    if [[ "$status" == "OK" || "$status" == "OK (new pack)" ]]; then
      color="$GREEN"
    else
      color="$RED"
    fi
    printf '%-50s  %-12s  %-13s  %-13s  %b%s%b\n' "$slug" "$pr_v" "$ppd_v" "$prod_v" "$color" "$status" "$RESET"
  done <<< "$slugs"

  echo "$sep"
  if [[ "$pr_merged" == "true" ]]; then
    printf 'Total: %d slugs — OK: %d  |  Missing PPD: %d  |  Missing PROD: %d  |  Missing both: %d  |  Wrong version: %d\n' \
      "$slug_count" "$ok" "$missing_ppd" "$missing_prod" "$missing_both" "$wrong_version"
  else
    printf 'Total: %d slugs — OK: %d  |  OK (new pack): %d  |  Missing PPD: %d  |  Wrong version: %d\n' \
      "$slug_count" "$ok" "$ok_new" "$missing_ppd" "$wrong_version"
  fi
}

# ---------------------------------------------------------------
# Step 0: what to check
# ---------------------------------------------------------------
echo "┌──────────────────────────────────────┐"
echo "│  Centreon Package Inventory           │"
echo "└──────────────────────────────────────┘"
echo ""
echo "Step 0 — What do you want to check?"
echo ""
echo "  [1] oss / modules / collect"
echo "  [2] centreon-plugins"
echo "  [3] centreon-plugin-packs"
echo "  [4] centreon-plugin-packs middleware (preprod/prod)"
echo "  [5] Stream connectors"
echo ""
read -rp "Choice [1]: " check_mode || true
check_mode="${check_mode//$'\r'/}"

if [[ "$check_mode" == "2" ]]; then
  run_plugins_check
  exit 0
fi

if [[ "$check_mode" == "3" ]]; then
  run_packs_check
  exit 0
fi

if [[ "$check_mode" == "4" ]]; then
  run_middleware_check
  exit 0
fi

if [[ "$check_mode" == "5" ]]; then
  run_stream_connectors_check
  exit 0
fi

# ---------------------------------------------------------------
# Step 1: repo stage
# ---------------------------------------------------------------
echo ""
echo "Step 1 — Repo stage?"
echo ""
echo "  [1] stable"
echo "  [2] testing-release"
echo "  [3] testing-hotfix"
echo "  [4] unstable"
echo ""
read -rp "Choice [1]: " stage_choice || true
stage_choice="${stage_choice//$'\r'/}"
stage_choice="${stage_choice:-1}"

case "$stage_choice" in
  2) STAGE="testing-release" ;;
  3) STAGE="testing-hotfix" ;;
  4) STAGE="unstable" ;;
  *) STAGE="stable" ;;
esac

# testing-release / testing-hotfix drop the "RPMS/" path segment that
# stable uses (component folders sit directly under noarch/ or x86_64/).
RPMS_SEG="RPMS/"
[[ "$STAGE" != "stable" ]] && RPMS_SEG=""

# ---------------------------------------------------------------
# Step 2: version / train
# ---------------------------------------------------------------
echo ""
echo "Step 2 — Version(s)?"
echo ""
for i in "${!AVAILABLE_TRAINS[@]}"; do
  printf "  [%d] %s\n" "$((i + 1))" "${AVAILABLE_TRAINS[$i]}"
done
echo "  [$((${#AVAILABLE_TRAINS[@]} + 1))] all"
echo ""
echo "Enter version numbers (space-separated), or 'all':"
read -r train_selection || true
train_selection="${train_selection//$'\r'/}"

TRAINS=()
if [[ "$train_selection" == "all" || "$train_selection" == "$((${#AVAILABLE_TRAINS[@]} + 1))" ]]; then
  TRAINS=("${AVAILABLE_TRAINS[@]}")
else
  for num in $train_selection; do
    idx=$((num - 1))
    if [[ $idx -ge 0 && $idx -lt ${#AVAILABLE_TRAINS[@]} ]]; then
      TRAINS+=("${AVAILABLE_TRAINS[$idx]}")
    else
      echo "Warning: invalid selection '$num', skipping."
    fi
  done
fi

if [[ ${#TRAINS[@]} -eq 0 ]]; then
  echo "No version selected. Exiting."
  exit 0
fi

# ---------------------------------------------------------------
# Step 3: components
# ---------------------------------------------------------------
echo ""
echo "Step 3 — Components?"
echo ""
declare -A GROUP_LABEL=(
  [oss]="OSS"
  [modules]="Modules"
  [collect]="Collect"
)
last_group=""
i=0
for entry in "${ORDERED_COMPONENTS[@]}"; do
  IFS='|' read -r name group arch base <<< "$entry"
  i=$((i + 1))
  if [[ "$group" != "$last_group" ]]; then
    echo "  -- ${GROUP_LABEL[$group]} --"
    last_group="$group"
  fi
  printf "  [%d] %s\n" "$i" "$name"
done
echo ""
echo "Enter component numbers (space-separated), keywords (oss / modules / collect), or 'all':"
read -r comp_selection || true
comp_selection="${comp_selection//$'\r'/}"

declare -A ALREADY_SELECTED
SELECTED=()
add_entry() {
  local entry="$1"
  [[ -n "${ALREADY_SELECTED[$entry]:-}" ]] && return
  ALREADY_SELECTED[$entry]=1
  SELECTED+=("$entry")
}

for token in $comp_selection; do
  case "$token" in
    all)
      for entry in "${ORDERED_COMPONENTS[@]}"; do add_entry "$entry"; done
      ;;
    oss)
      for entry in "${ORDERED_COMPONENTS[@]}"; do
        [[ "$entry" == *"|oss|"* ]] && add_entry "$entry"
      done
      ;;
    modules)
      for entry in "${ORDERED_COMPONENTS[@]}"; do
        [[ "$entry" == *"|modules|"* ]] && add_entry "$entry"
      done
      ;;
    collect)
      for entry in "${ORDERED_COMPONENTS[@]}"; do
        [[ "$entry" == *"|collect|"* ]] && add_entry "$entry"
      done
      ;;
    ''|*[!0-9]*)
      echo "Warning: invalid selection '$token', skipping."
      ;;
    *)
      idx=$((token - 1))
      if [[ $idx -ge 0 && $idx -lt ${#ORDERED_COMPONENTS[@]} ]]; then
        add_entry "${ORDERED_COMPONENTS[$idx]}"
      else
        echo "Warning: invalid selection '$token', skipping."
      fi
      ;;
  esac
done

if [[ ${#SELECTED[@]} -eq 0 ]]; then
  echo "No components selected. Exiting."
  exit 0
fi

# ---------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------

component_url() {
  local name="$1" train="$2" os="$3" arch="$4" base="$5" stage="$6"
  local root="$STD_BASE"
  [[ "$base" == "BIZ" ]] && root="$BIZ_BASE"
  local seg="RPMS/"
  [[ "$stage" != "stable" ]] && seg=""
  echo "${root}/${train}/${os}/${stage}/${arch}/${seg}${name}/"
}

last_two_versions() {
  # $1 = html, $2 = os
  local html="$1" os="$2"
  echo "$html" | grep -oE "\-[0-9]+\.[0-9]+\.[0-9]+-[0-9]+\.${os}" \
    | sed -E "s/^-//; s/-[0-9]+\.${os}\$//" \
    | sort -V -u | tail -2
}

version_files() {
  # $1 = html, $2 = version, $3 = os -- prints matching filenames, one per line
  local html="$1" version="$2" os="$3"
  echo "$html" | grep -oE "href=\"[^\"]*-${version}-[0-9]+\.${os}\.[a-z0-9_]*\.rpm\"" \
    | sed 's/href="//; s/"//' | sort
}

version_dates() {
  # $1 = html, $2 = version, $3 = os -- prints the upload day(s) (DD-Mon-YYYY)
  # for files matching version, comma-joined (usually just one).
  local html="$1" version="$2" os="$3"
  local line rest
  while IFS= read -r line; do
    [[ "$line" =~ href=\"[^\"]*-${version}-[0-9]+\.${os}\.[a-z0-9_]*\.rpm\" ]] || continue
    rest="${line#*</a>}"
    [[ "$rest" =~ ([0-9]{2}-[A-Za-z]{3}-[0-9]{4}) ]] && echo "${BASH_REMATCH[1]}"
  done <<< "$html" | sort -u | paste -sd, -
}

basename_no_version() {
  # Strip "-<version>-<release>.<os>.<arch>.rpm" so files can be compared
  # across versions by package identity rather than by exact filename.
  local file="$1" version="$2" os="$3"
  echo "$file" | sed -E "s/-${version}-[0-9]+\.${os}\..*\.rpm\$//"
}

basename_any_version() {
  # Strip "-<any-version>-<release>.<os>.<arch>.rpm" without knowing the
  # version up front. Used for testing-release/testing-hotfix, which may
  # hold an older or newer version than stable's latest.
  local file="$1" os="$2"
  echo "$file" | sed -E "s/-[0-9]+\.[0-9]+\.[0-9]+-[0-9]+\.${os}\..*\.rpm\$//"
}

size_to_bytes() {
  # Convert an Artifactory-style size string ("5.26 KB", "204.91 MB",
  # "12 bytes") to a plain byte count. Artifactory adds a thousands-separator
  # comma once the number reaches 1000 in its unit (e.g. "1,007.89 KB").
  local num unit
  num=$(echo "$1" | awk '{print $1}' | tr -d ',')
  unit=$(echo "$1" | awk '{print $2}')
  case "$unit" in
    bytes) awk -v n="$num" 'BEGIN{printf "%.0f", n}' ;;
    KB)    awk -v n="$num" 'BEGIN{printf "%.0f", n*1024}' ;;
    MB)    awk -v n="$num" 'BEGIN{printf "%.0f", n*1024*1024}' ;;
    GB)    awk -v n="$num" 'BEGIN{printf "%.0f", n*1024*1024*1024}' ;;
    *)     echo 0 ;;
  esac
}

parse_listing() {
  # $1 = html. Prints "filename|date|size_bytes" for every .rpm entry.
  local html="$1"
  local line fname rest date size
  while IFS= read -r line; do
    [[ "$line" =~ href=\"([^\"]+\.rpm)\" ]] || continue
    fname="${BASH_REMATCH[1]}"
    # Everything after the closing </a> is "  DATE  SIZE UNIT"
    rest="${line#*</a>}"
    if [[ "$rest" =~ ([0-9]{2}-[A-Za-z]{3}-[0-9]{4}\ [0-9]{2}:[0-9]{2})[[:space:]]+([0-9,.]+[[:space:]]*[A-Za-z]+) ]]; then
      date="${BASH_REMATCH[1]}"
      size=$(size_to_bytes "${BASH_REMATCH[2]}")
    else
      date="unknown"
      size=0
    fi
    echo "${fname}|${date}|${size}"
  done <<< "$html"
}

# ---------------------------------------------------------------
# Main
# ---------------------------------------------------------------

check_testing_stage() {
  # Validate a testing-release/testing-hotfix component against 3 criteria:
  #   1. package set matches stable's latest version (by package identity)
  #   2. every package was built on the same day
  #   3. every package is at least 1 KB
  local name="$1" group="$2" arch="$3" base="$4" train="$5" os="$6"

  local test_url stable_url
  test_url=$(component_url "$name" "$train" "$os" "$arch" "$base" "$STAGE")
  stable_url=$(component_url "$name" "$train" "$os" "$arch" "$base" "stable")

  local test_html stable_html
  test_html=$(curl -s -f "$test_url" 2>/dev/null || true)
  if [[ -z "$test_html" ]]; then
    echo "${YELLOW}($STAGE): could not fetch (404 or empty) — $test_url${RESET}"
    return
  fi

  mapfile -t entries < <(parse_listing "$test_html")
  if [[ ${#entries[@]} -eq 0 ]]; then
    echo "${YELLOW}($STAGE): no packages found${RESET}"
    return
  fi

  local versions_seen
  versions_seen=$(for e in "${entries[@]}"; do
    echo "${e%%|*}" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+-[0-9]+' | sed -E 's/-[0-9]+$//'
  done | sort -V -u | paste -sd, -)

  local ok=1
  local -a problems=()

  # --- Check 1: package set vs stable's latest version -----------------
  stable_html=$(curl -s -f "$stable_url" 2>/dev/null || true)
  if [[ -n "$stable_html" ]]; then
    mapfile -t stable_versions < <(last_two_versions "$stable_html" "$os")
    if [[ ${#stable_versions[@]} -gt 0 ]]; then
      local stable_latest="${stable_versions[-1]}"
      mapfile -t stable_files < <(version_files "$stable_html" "$stable_latest" "$os")

      local expected_file actual_file
      expected_file=$(mktemp); actual_file=$(mktemp)
      for f in "${stable_files[@]}"; do
        basename_no_version "$f" "$stable_latest" "$os"
      done | sort -u > "$expected_file"
      for e in "${entries[@]}"; do
        basename_any_version "${e%%|*}" "$os"
      done | sort -u > "$actual_file"

      local missing new
      missing=$(comm -23 "$expected_file" "$actual_file")
      new=$(comm -13 "$expected_file" "$actual_file")
      rm -f "$expected_file" "$actual_file"

      if [[ -n "$missing" || -n "$new" ]]; then
        ok=0
        [[ -n "$missing" ]] && while IFS= read -r p; do
          [[ -n "$p" ]] && problems+=("missing package (present in stable $stable_latest, absent here): $p")
        done <<< "$missing"
        [[ -n "$new" ]] && while IFS= read -r p; do
          [[ -n "$p" ]] && problems+=("unexpected extra package (not in stable $stable_latest): $p")
        done <<< "$new"
      fi
    fi
  fi

  # --- Check 2: all built the same day ----------------------------------
  local dates_seen
  dates_seen=$(for e in "${entries[@]}"; do echo "$e" | cut -d'|' -f2 | cut -d' ' -f1; done | sort -u)
  local date_count
  date_count=$(echo "$dates_seen" | grep -c . || true)
  if [[ "$date_count" -gt 1 ]]; then
    ok=0
    local majority_date
    majority_date=$(for e in "${entries[@]}"; do echo "$e" | cut -d'|' -f2 | cut -d' ' -f1; done | sort | uniq -c | sort -rn | head -1 | awk '{print $2}')
    for e in "${entries[@]}"; do
      local fname="${e%%|*}"
      local d
      d=$(echo "$e" | cut -d'|' -f2 | cut -d' ' -f1)
      if [[ "$d" != "$majority_date" ]]; then
        problems+=("built on a different day ($d, most are $majority_date): $fname")
      fi
    done
  fi

  # --- Check 3: at least 1 KB (1024 bytes) ------------------------------
  for e in "${entries[@]}"; do
    local fname size
    fname="${e%%|*}"
    size="${e##*|}"
    if [[ "$size" -lt 1024 ]]; then
      ok=0
      problems+=("smaller than 1 KB (${size} bytes): $fname")
    fi
  done

  if [[ "$ok" -eq 1 ]]; then
    printf "${YELLOW}(%s)${RESET}: OK — latest version %s\n" "$STAGE" "$versions_seen"
    printf "%d packages, all built %s, all >= 1 KB\n" "${#entries[@]}" "$(echo "$dates_seen" | head -1)"
  else
    printf "${RED}${BOLD}(%s): PROBLEM — latest version %s${RESET}\n" "$STAGE" "$versions_seen"
    for p in "${problems[@]}"; do
      echo "  ${RED}- $p${RESET}"
    done
  fi
}

# Set by find_testing_match(): which testing repo (if any) still carries the
# requested version, its package count, upload date, and any diff vs the
# names passed in.
TESTMATCH_STAGE=""
TESTMATCH_COUNT=0
TESTMATCH_DATE=""
TESTMATCH_MISSING=""
TESTMATCH_NEW=""

find_testing_match() {
  # Look for $version in testing-release, then testing-hotfix (first match
  # wins), and diff its package set against the basenames in $7.
  local name="$1" arch="$2" base="$3" train="$4" os="$5" version="$6"
  local -n names_ref=$7

  TESTMATCH_STAGE=""
  TESTMATCH_COUNT=0
  TESTMATCH_DATE=""
  TESTMATCH_MISSING=""
  TESTMATCH_NEW=""

  for test_stage in testing-release testing-hotfix; do
    local url html
    url=$(component_url "$name" "$train" "$os" "$arch" "$base" "$test_stage")
    html=$(curl -s -f "$url" 2>/dev/null || true)
    [[ -z "$html" ]] && continue

    mapfile -t test_files < <(version_files "$html" "$version" "$os")
    [[ ${#test_files[@]} -eq 0 ]] && continue

    TESTMATCH_STAGE="$test_stage"
    TESTMATCH_COUNT=${#test_files[@]}
    TESTMATCH_DATE=$(version_dates "$html" "$version" "$os")

    local test_names_file names_file
    test_names_file=$(mktemp); names_file=$(mktemp)
    for f in "${test_files[@]}"; do
      basename_no_version "$f" "$version" "$os"
    done | sort -u > "$test_names_file"
    printf '%s\n' "${names_ref[@]}" | sort -u > "$names_file"
    TESTMATCH_MISSING=$(comm -23 "$names_file" "$test_names_file")
    TESTMATCH_NEW=$(comm -13 "$names_file" "$test_names_file")
    rm -f "$test_names_file" "$names_file"
    break
  done
}

echo ""
echo "Fetching from packages.centreon.com (stage: $STAGE)..."
echo ""

for entry in "${SELECTED[@]}"; do
  IFS='|' read -r name group arch base <<< "$entry"

  if [[ "$STAGE" != "stable" ]]; then
    echo "[$name]"
    echo ""
    for train in "${TRAINS[@]}"; do
      for os in $(oses_for_train "$train"); do
        echo "$train $os"
        check_testing_stage "$name" "$group" "$arch" "$base" "$train" "$os"
        echo ""
      done
    done
    continue
  fi

  echo "[$name]"
  echo ""

  for train in "${TRAINS[@]}"; do
    for os in $(oses_for_train "$train"); do
      url=$(component_url "$name" "$train" "$os" "$arch" "$base" "$STAGE")
      html=$(curl -s -f "$url" 2>/dev/null || true)

      echo "$train $os"

      if [[ -z "$html" ]]; then
        echo "  ${YELLOW}could not fetch (404 or empty) — $url${RESET}"
        echo ""
        continue
      fi

      mapfile -t versions < <(last_two_versions "$html" "$os")

      if [[ ${#versions[@]} -eq 0 ]]; then
        echo "  ${YELLOW}no versions found${RESET}"
        echo ""
        continue
      fi

      if [[ ${#versions[@]} -eq 1 ]]; then
        v="${versions[0]}"
        count=$(version_files "$html" "$v" "$os" | wc -l)
        date=$(version_dates "$html" "$v" "$os")
        printf "${YELLOW}Latest${RESET}: %s -> %d packages (only version found, uploaded %s)\n" "$v" "$count" "$date"
        echo ""
        continue
      fi

      prev_v="${versions[0]}"
      latest_v="${versions[1]}"
      mapfile -t prev_files < <(version_files "$html" "$prev_v" "$os")
      prev_count=${#prev_files[@]}
      mapfile -t latest_files < <(version_files "$html" "$latest_v" "$os")
      latest_count=${#latest_files[@]}

      mapfile -t latest_names < <(for f in "${latest_files[@]}"; do
        basename_no_version "$f" "$latest_v" "$os"
      done | sort -u)
      mapfile -t prev_names < <(for f in "${prev_files[@]}"; do
        basename_no_version "$f" "$prev_v" "$os"
      done | sort -u)

      latest_date=$(version_dates "$html" "$latest_v" "$os")
      printf "${YELLOW}Latest${RESET}: %s -> %d packages (uploaded %s)\n" "$latest_v" "$latest_count" "$latest_date"

      # --- vs testing-release / testing-hotfix ---
      find_testing_match "$name" "$arch" "$base" "$train" "$os" "$latest_v" latest_names
      if [[ -z "$TESTMATCH_STAGE" ]]; then
        printf "${YELLOW}Testing-release/hotfix: %s not found${RESET}\n" "$latest_v"
      elif [[ -n "$TESTMATCH_MISSING$TESTMATCH_NEW" ]]; then
        printf "${RED}${BOLD}Testing-release/hotfix: %s: %d packages (uploaded %s)  DIFF${RESET}\n" \
          "$latest_v" "$TESTMATCH_COUNT" "$TESTMATCH_DATE"
        while IFS= read -r p; do
          [[ -n "$p" ]] && echo "  ${RED}- in stable but not in $TESTMATCH_STAGE: $p${RESET}"
        done <<< "$TESTMATCH_MISSING"
        while IFS= read -r p; do
          [[ -n "$p" ]] && echo "  ${RED}- in $TESTMATCH_STAGE but not in stable: $p${RESET}"
        done <<< "$TESTMATCH_NEW"
      else
        printf "Testing-release/hotfix: %s: %d packages (uploaded %s)\n" "$latest_v" "$TESTMATCH_COUNT" "$TESTMATCH_DATE"
      fi

      # --- vs previous stable version ---
      prev_date=$(version_dates "$html" "$prev_v" "$os")
      if [[ "$prev_count" -eq "$latest_count" ]]; then
        printf "Previous stable: %s -> %d packages (uploaded %s)\n" "$prev_v" "$prev_count" "$prev_date"
      else
        printf "${RED}${BOLD}Previous stable: %s -> %d packages (uploaded %s)  DIFF${RESET}\n" "$prev_v" "$prev_count" "$prev_date"

        prev_names_file=$(mktemp); latest_names_file=$(mktemp)
        trap 'rm -f "$prev_names_file" "$latest_names_file"' RETURN
        printf '%s\n' "${prev_names[@]}" | sort -u > "$prev_names_file"
        printf '%s\n' "${latest_names[@]}" | sort -u > "$latest_names_file"

        missing=$(comm -23 "$prev_names_file" "$latest_names_file")
        new=$(comm -13 "$prev_names_file" "$latest_names_file")
        rm -f "$prev_names_file" "$latest_names_file"
        trap - RETURN

        while IFS= read -r pkg; do
          [[ -n "$pkg" ]] && echo "  ${RED}- missing in $latest_v: $pkg${RESET}"
        done <<< "$missing"
        while IFS= read -r pkg; do
          [[ -n "$pkg" ]] && echo "  ${YELLOW}- new in $latest_v: $pkg${RESET}"
        done <<< "$new"
      fi

      echo ""
    done
  done
done
