#!/bin/bash

# Fail fast: exit on any error and on failures inside pipelines.
# 'set -u' is intentionally NOT enabled: this script relies on many optional unset
# variables (use_vault, requested_*, ENV_*, ...). 'errtrace' makes the ERR trap fire
# inside functions, subshells and command substitutions too.
set -Eeo pipefail
set -o errtrace

#========= begin of function on_error()
# ERR trap handler: report any *unanticipated* command failure with a timestamp,
# exit code, line number and the failing command, then exit with that code.
# Commands that may carry secrets are redacted so nothing sensitive reaches the
# persistent log file or the console error line (this does NOT touch the intended
# credential channels: /etc/centreon/generated.tobesecured and the fd-3 recap).
function on_error() {
	local code="${1}" line="${2}" cmd="${3}"
	case "${cmd}" in
		*IDENTIFIED\ BY*|*IDENTIFIED\ WITH*|*--data*|*-p\"*|*password*|*genpasswd*|*X-AUTH-TOKEN*|*centreon-auth-token*|*token=*)
			cmd="<command redacted>" ;;
	esac
	printf '%s - ERROR - command failed (exit %s) at line %s: %s\n' \
		"$(date '+%Y-%m-%d %H:%M:%S.%3N%:z')" "${code}" "${line}" "${cmd}" >&2
	# Surface the failure on the real console (fd 3) too, so a provisioning run that
	# sends all output to the log file still shows that (and where) it failed.
	if [ -n "${LOG_FILE:-}" ]; then
		printf 'unattended.sh FAILED (exit %s at line %s) - see %s\n' "${code}" "${line}" "${LOG_FILE}" >&3
	fi
	exit "${code}"
}
#========= end of function on_error()
trap 'on_error "$?" "${LINENO}" "${BASH_COMMAND}"' ERR

### Define all supported constants
OPTIONS="hsDt:v:r:l:p:d:V:-:"
declare -A SUPPORTED_LOG_LEVEL=([DEBUG]=0 [INFO]=1 [WARN]=2 [ERROR]=3)
declare -A SUPPORTED_TOPOLOGY=([central]=1 [poller]=1)
declare -A SUPPORTED_VERSION=([24.10]=1 [25.10]=1 [26.07]=1)
declare -A SUPPORTED_REPOSITORY=([testing-hotfix]=1 [testing-release]=1 [unstable]=1 [stable]=1)
declare -A SUPPORTED_DBMS=([MariaDB]=1 [MySQL]=1)
declare -A SUPPORTED_TLS=([enabled]=1 [disabled]=1)
default_timeout_in_sec=5
script_short_name="$(basename $0)"
default_ip=$(hostname -I | awk '{print $1}') || true
###

#Define default values

passwords_file=/etc/centreon/generated.tobesecured         #File where the generated passwords will be temporaly saved
tmp_passwords_file=$(mktemp /tmp/generated.XXXXXXXXXXXXXX) #Random tmp file as the /etc/centreon does not exist yet

topology=${ENV_CENTREON_TOPOLOGY:-"central"}    #Default topology to be installed
version=${ENV_CENTREON_VERSION:-"25.10"}        #Default version to be installed
repo=${ENV_CENTREON_REPO:-"stable"}             #Default repository to be used
dbms=${ENV_CENTREON_DBMS:-"MariaDB"}            #Default database system to be used
operation=${ENV_CENTREON_OPERATION:-"install"}  #Default operation to be executed
runtime_log_level=${ENV_LOG_LEVEL:-"INFO"}      #Default log level to be used
selinux_mode=${ENV_SELINUX_MODE:-"permissive"}  #Default SELinux mode to be used
wizard_autoplay=${ENV_WIZARD_AUTOPLAY:-"false"} #Default the install wizard is not run auto
central_ip=${ENV_CENTRAL_IP:-$default_ip}       #Default central ip is the first of hostname -I
tls=${ENV_DB_TLS:-"disabled"}                   # default DB TLS mode
debug_mode=${ENV_DEBUG_MODE:-"false"}           #Default debug mode (set -x xtrace) is disabled
LOG_FILE=""                                      #Path of the log file (set up in main, see ENV_LOG_FILE)

function genpasswd() {
	local _pwd

	# 'head -c4' closes the pipe early, so 'tr' receives SIGPIPE and exits non-zero;
	# under 'pipefail' that would trip the ERR trap. Guard each pipeline with '|| true'.
	PWD_LOWER=$(tr -dc '[:lower:]' </dev/urandom | head -c4) || true
	PWD_UPPER=$(tr -dc '[:upper:]' </dev/urandom | head -c4) || true
	PWD_DIGIT=$(tr -dc '[:digit:]' </dev/urandom | head -c4) || true
	PWD_SPECIAL=$(tr -dc '!?@*' </dev/urandom | head -c4) || true

	_pwd="$PWD_LOWER$PWD_UPPER$PWD_DIGIT$PWD_SPECIAL"
	_pwd=$(echo $_pwd |fold -w 1 |shuf |tr -d '\n') || true

	if ! echo "Random password generated for user [$1] is [$_pwd]" >>$tmp_passwords_file; then
		echo "ERROR: Cannot save the random password to [$tmp_passwords_file]"
		exit 1
	fi

	#return the generated password
	echo $_pwd
}

CENTREON_MAJOR_VERSION=$version
CENTREON_RELEASE_VERSION="$CENTREON_MAJOR_VERSION-1"

# Static variables
PHP_BIN="/usr/bin/php"
PHP_ETC="/etc/php.d/"

# Variables dynamically set
detected_os_release=
detected_os_version=
detected_os_major=
detected_mariadb_version=
mysql_service_name=
centreon_admin_password=

# Variables will be defined later according to the target system OS
BASE_PACKAGES=
CENTREON_SELINUX_PACKAGES=
RELEASE_REPO_FILE=
PHP_SERVICE_UNIT=
HTTP_SERVICE_UNIT=
PKG_MGR=
has_systemd=
CENTREON_REPO=
CENTREON_DOC_URL=

#########################################################
############### ALL INTERNAL FUNCTIONS ##################

#========= begin of function usage()
# display help usage
#
function usage() {

	echo
	echo "Usage:"
	echo
	echo " $script_short_name [install|update (default: install)] [-t <central|poller> (default: central)] [-v <24.10|25.10|26.07> (default: 25.10)] [-r <stable|testing-hotfix|testing-release|unstable> (default: stable)] [-d <MariaDB|MySQL> (default: MariaDB)] [--tls <enabled|disabled> (default: disabled)] [-l <DEBUG|INFO|WARN|ERROR>] [-s (for silent install)] [-p <centreon admin password>] [-D (enable debug mode: shell xtrace + DEBUG log level)] [-h (show this help output)] [-V configure a vault, using format <address>;<port>;<root_path>;<role_id>;<secret_id>]"
	echo
	echo Example:
	echo
	echo " $script_short_name == install the $version of $topology from the repository $repo"
	echo
	echo " $script_short_name install -r unstable,testing == install the central to the $version from the unstable & testing repository"
	echo
	echo " $script_short_name install -V vault-example.com;8200;my_storage;my-role-id;my-secret-id == configuring a vault to store your application and database credentials"
	exit 1
}
#======== end of function usage()

#========= begin of function log()
# print out the message according to the level
# with timestamp
#
# usage:
# log "$LOG_LEVEL" "$message" ($LOG_LEVEL = DEBUG|INFO|WARN|ERROR)
#
# example:
# log "DEBUG" "This is a DEBUG_LOG_LEVEL message"
# log "INFO" "This is a INFO_LOG_LEVEL message"
#
function log() {

	TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S.%3N%:z')

	if [[ -z "${1}" || -z "${2}" ]]; then
		echo "${TIMESTAMP} - ERROR: Missing argument"
		echo "${TIMESTAMP} - ERROR: Usage log \"INFO\" \"Message log\" "
		exit 1
	fi

	# get the message log level
	log_message_level="${1}"

	# shift once to get the log message (string or array)
	shift

	# get the log message (full log message)
	log_message="${@}"

	# Skip messages whose level is unknown or below the configured runtime level.
	# Always return 0 (suppression is a success): a bare "log" call returning a
	# non-zero status would otherwise abort the script under 'set -e'.
	[[ ${SUPPORTED_LOG_LEVEL[$log_message_level]} ]] || return 0

	if ((${SUPPORTED_LOG_LEVEL[$log_message_level]} < ${SUPPORTED_LOG_LEVEL[$runtime_log_level]})); then
		return 0
	fi

	echo -e "${TIMESTAMP} - $log_message_level - $log_message"
	return 0

}
#======== end of function log()

#========= begin of function version_int()
# convert "YY.MM" to a comparable integer (24.10 -> 2410, 26.07 -> 2607) for arithmetic version checks.
# defaults to the global $version.
#
function version_int() {
	local v="${1:-$version}"
	# 10# forces base-10 so a leading-zero minor like "07" is not parsed as octal.
	echo $((10#${v%%.*} * 100 + 10#${v##*.}))
}
#========= end of function version_int()

#========= begin of function uses_internal_repo()
# true when $version ships only in internal repos: public repos carry the ".10" GA minors, internal the rest
#
function uses_internal_repo() {
	[[ "${version##*.}" != "10" ]]
}
#========= end of function uses_internal_repo()

#========= begin of function parse_subcommand_options()
# parse the provided arguments and check values
# the script will display usage (and aborted) for any
# unsupported argument/option (which are defined in constants)
#
function parse_subcommand_options() {
	local requested_topology=""
	local requested_version=""
	local requested_repo=""
	local OPTIND
	OPTIND=2
	while getopts $OPTIONS opt; do
		case ${opt} in
		t)
			requested_topology=$OPTARG
			log "INFO" "Requested topology: '$requested_topology'"

			[[ ! ${SUPPORTED_TOPOLOGY[$requested_topology]} ]] &&
				log "ERROR" "Unsupported topology: $requested_topology" &&
				usage
			;;

		v)
			requested_version=$OPTARG
			log "INFO" "Requested version: '$requested_version'"

			[[ ! ${SUPPORTED_VERSION[$requested_version]} ]] &&
				log "ERROR" "Unsupported version: $requested_version" &&
				usage
			;;

		r)
			requested_repo=$OPTARG
			log "INFO" "Requested repository: '$requested_repo'"
			# repos are resolved later in set_required_prerequisite, once $version is finalized
			;;

		l)
			log_level=$OPTARG
			if ! [[ ${SUPPORTED_LOG_LEVEL[$log_level]} ]]; then
				log "ERROR" "Unsupported and ignored log level: $log_level"
				usage
			else
				runtime_log_level=$log_level
			fi
			log "INFO" "Runtime log level set: $runtime_log_level"
			;;

		s)
			wizard_autoplay="true"
			log "INFO" "The installation wizard will be executed by the script"
			;;

		D)
			debug_mode="true"
			runtime_log_level="DEBUG"
			log "INFO" "Debug mode requested (shell xtrace + DEBUG log level)"
			;;

		p)
			centreon_admin_password=$OPTARG
			;;

		d)
			requested_dbms=$OPTARG
			if [ ! ${SUPPORTED_DBMS[$requested_dbms]} ]; then
				log "ERROR" "Unsupported database system: $requested_dbms" &&
				usage
			else
				dbms=$requested_dbms
			fi
			;;
		V)
			vault=$OPTARG
			oldIFS=$IFS
			IFS=';' read -r -a array_vault <<<"$vault"
			vault_address=${array_vault[0]}
			vault_port=${array_vault[1]}
			vault_root_path=${array_vault[2]}
			vault_role_id=${array_vault[3]}
			vault_secret_id=${array_vault[4]}
			use_vault=1
			IFS=$oldIFS
			;;
		-)
			# GNU-style long options (getopts is short-only): support both
			# "--name=value" and "--name value" forms.
			long_opt="${OPTARG%%=*}"
			if [[ "$OPTARG" == *=* ]]; then
				long_optarg="${OPTARG#*=}"
			else
				long_optarg="${!OPTIND}"
				OPTIND=$((OPTIND + 1))
			fi
			case "$long_opt" in
			tls)
				if [[ ! ${SUPPORTED_TLS[$long_optarg]} ]]; then
					log "ERROR" "Unsupported TLS mode: $long_optarg"
					usage
				fi
				tls=$long_optarg
				log "INFO" "Requested DB TLS mode: '$tls'"
				;;
			*)
				log "ERROR" "Invalid option: --$long_opt"
				usage
				exit 1
				;;
			esac
			;;
		\?)
			log "ERROR" "Invalid option: -"$OPTARG""
			usage
			exit 1
			;;

		h)
			usage
			exit 0
			;;

		:)
			log "ERROR" "Option -"$OPTARG" requires an argument."
			usage
			exit 1
			;;

		esac
	done
	shift $((OPTIND - 1))

	## check the configuration parameters
	if [ -z "${requested_topology}" ]; then
		log "WARN" "No topology provided: default value [$topology] will be used"
	else
		topology=$requested_topology
	fi

	if [ -z "${requested_version}" ]; then
		log "WARN" "No version provided: default value [$version] will be used"
	else
		version=$requested_version
	fi

	if [ -z "${requested_repo}" ]; then
		log "WARN" "No repository provided: default value [$repo] will be used"
	else
		repo=$requested_repo
	fi
}
#======== end of function parse_subcommand_options()

#========= begin of function setup_debug_mode()
# Enable shell xtrace when debug mode is requested (via the -D flag or
# ENV_DEBUG_MODE=true) and force the DEBUG log level. The PS4 prefix carries a
# millisecond timestamp and the source/line/function so every traced line is locatable.
#
function setup_debug_mode() {
	if [ "${debug_mode}" == "true" ]; then
		runtime_log_level="DEBUG"
		export PS4='+ $(date "+%H:%M:%S.%3N") ${BASH_SOURCE##*/}:${LINENO}:${FUNCNAME[0]:-main}() '
		# Send xtrace (may expand passwords/tokens) to a dedicated 0600 file so the main log stays secret-free.
		if [ -n "${LOG_FILE:-}" ]; then
			DEBUG_TRACE_FILE="${LOG_FILE%.log}.debug.log"
			if { : > "$DEBUG_TRACE_FILE"; } 2>/dev/null && exec 9>>"$DEBUG_TRACE_FILE"; then
				chmod 600 "$DEBUG_TRACE_FILE" 2>/dev/null || true
				export BASH_XTRACEFD=9
				log "WARN" "Debug trace written to [$DEBUG_TRACE_FILE] - it MAY CONTAIN CREDENTIALS; handle/delete it securely"
				echo "unattended.sh: debug trace (may contain credentials) -> $DEBUG_TRACE_FILE" >&3
			fi
		fi
		set -x
	fi
}
#========= end of function setup_debug_mode()

#========= begin of function notice()
# Announce an installation milestone: record it (INFO) in the log file and mirror
# it to the real console (fd 3) when output is redirected to a log file, so the
# operator can follow progress in both places during the long-running steps.
#
function notice() {
	log "INFO" ">>> $1"
	if [ -n "${LOG_FILE:-}" ]; then
		printf '>>> %s\n' "$1" >&3
	fi
}
#========= end of function notice()

#========= begin of function error_and_exit()
# display the ERROR log message then exit the script
function error_and_exit() {
	log "ERROR" "$1"
	# When output is redirected to a log file, also surface the failure (and where to
	# look) on the real console (fd 3), so a provisioning run does not fail silently.
	if [ -n "${LOG_FILE:-}" ]; then
		printf 'unattended.sh FAILED: %s - see %s\n' "$1" "${LOG_FILE}" >&3
	fi
	exit 1
}
#========= end of function error_and_exit()

#========= begin of function pause()
# add pause prompt message ($1) for ($2) seconds
#
function pause() {
	local timeout=$default_timeout_in_sec
	if [ -n "${2:-}" ]; then
		timeout=$2
	fi
	# Returns 0 if a key was pressed within the timeout, non-zero if the timeout elapsed,
	# so the caller can report whether the operator skipped the wait.
	local rc=0
	read -t $timeout -s -n 1 -p "${1}" || rc=$?
	echo ""
	return $rc
}
#========= end of function pause()

#========= begin of function get_os_information()
# get the OS release
# if the detected release is not supported the script will be ended
#
function get_os_information() {

	# Get OS name
	OS_NAME=$(grep "^NAME=" /etc/os-release | cut -d'=' -f2 | tr -d '"')
	# Get OS version
	OS_VERSIONID=$(grep "^VERSION_ID=" /etc/os-release | cut -d'=' -f2 | tr -d '"')

	if [[ "$(echo "${OS_NAME}" | wc -l)" -ne 1 || "$(echo "${OS_VERSIONID}" | wc -l)" -ne 1 ]]; then
		error_and_exit "Unable to determine your running OS or version."
	fi

	case "${OS_NAME}" in
		AlmaLinux*)
			detected_os_release="almalinux-release-${OS_VERSIONID}"
			mysql_service_name="mysqld"
			;;
		CentOS*)
			detected_os_release="centos-release-${OS_VERSIONID}"
			mysql_service_name="mysqld"
			;;
		Debian*)
			case "${OS_VERSIONID}" in
				13*)
					detected_os_release="debian-release-${OS_VERSIONID}"
					mysql_service_name="mysql"
					;;
				12*)
					detected_os_release="debian-release-${OS_VERSIONID}"
					mysql_service_name="mysql"
					;;
				11*)
					error_and_exit "Debian ${OS_VERSIONID} is no longer supported by this script (Centreon 24.04 reached end of life). Please upgrade to Debian 12. See https://docs.centreon.com/docs/installation/introduction for alternative installation methods."
					;;
				*)
					error_and_exit "Unsupported Debian distribution ${OS_VERSIONID} detected"
					;;
			esac
			;;
		Oracle*)
			detected_os_release="oraclelinux-release-${OS_VERSIONID}"
			mysql_service_name="mysqld"
			;;
		"Red Hat"*)
			detected_os_release="redhat-release-${OS_VERSIONID}"
			mysql_service_name="mysqld"
			;;
		Rocky*)
			detected_os_release="rocky-release-${OS_VERSIONID}"
			mysql_service_name="mysqld"
			;;
		Ubuntu*)
			error_and_exit "Ubuntu is no longer supported by this script (only Centreon 24.04 was compatible, and it has reached end of life). Please use Debian 12 or a Red-Hat compatible distribution (v8/v9). See https://docs.centreon.com/docs/installation/introduction for alternative installation methods."
			;;
		*)
			log "ERROR" "Unsupported distribution ${OS_NAME} detected"
			error_and_exit "This '$script_short_name' script only supports Red-Hat compatible distribution (v8 and v9) and Debian 12. Please check https://docs.centreon.com/docs/installation/introduction for alternative installation methods."
			;;
	esac

	detected_os_version=${OS_VERSIONID}
	# Major version only (e.g. "10" from AlmaLinux "10.0", "13" from Debian "13"),
	# reused to build repo URLs and pick PHP/DB mechanisms.
	detected_os_major=${OS_VERSIONID%%.*}

	log "INFO" "Your running OS is $detected_os_release (version: ${detected_os_version})"

}
#========= end of function get_os_information()

#========= begin of function set_centreon_repos()
# split the repos from the args (separated by , )
# then concat the string for $CENTREON_REPO
#
function set_centreon_repos() {
	if ! [ -z $1 ]; then
		repo=$1
	fi

	IFS=', ' read -r -a array_repos <<<"$repo"

	CENTREON_REPO=""
	for _repo in "${array_repos[@]}"; do

		[[ ! ${SUPPORTED_REPOSITORY[$_repo]} ]] &&
			log "ERROR" "Unsupported repository: $_repo" &&
			usage

		if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
			CENTREON_REPO+="$version-$_repo"
		else
			CENTREON_REPO+="centreon-$version-$_repo*"
		fi

		if ! [ "$_repo" == "${array_repos[@]:(-1)}" ]; then
			CENTREON_REPO+=","
		fi
	done

	log "INFO" "Following Centreon repo will be used: [$CENTREON_REPO]"

}
#========= end of function set_centreon_repos()

#========= begin of function set_release_repo_file()
# build the RPM .repo URL for the detected RHEL major version ($detected_os_major)
# non-".10" versions are only published to the internal repository
#
function set_release_repo_file() {
	if uses_internal_repo; then
		RELEASE_REPO_FILE="https://packages.centreon.com/rpm-standard-internal/$version/el${detected_os_major}/centreon-$version-internal.repo"
	else
		RELEASE_REPO_FILE="https://packages.centreon.com/artifactory/rpm-standard/$version/el${detected_os_major}/centreon-$version.repo"
	fi
}
#========= end of function set_release_repo_file()

#========= begin of function install_mariadb_repo_setup()
# Download MariaDB's official repo-setup script to a file and run it, rather than piping it straight
# from the network into a root shell: this avoids executing a half-downloaded script if the
# connection drops, and lets curl fail cleanly on an HTTP error before anything runs.
# "$@" is forwarded to the script.
#
function install_mariadb_repo_setup() {
	local script rc
	script=$(mktemp)
	curl -fsSL https://r.mariadb.com/downloads/mariadb_repo_setup -o "$script" || error_and_exit "Failed to download mariadb_repo_setup"
	rc=0
	bash "$script" "$@" || rc=$?
	rm -f "$script"
	return $rc
}
#========= end of function install_mariadb_repo_setup()

#========= begin of function set_mariadb_repos()
#
function set_mariadb_repos() {
	log "INFO" "Install MariaDB repository"
	if (( $(version_int) >= $(version_int 26.07) )); then
		detected_mariadb_version="11.8"
	else
		detected_mariadb_version="10.11"
	fi

	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		install_mariadb_repo_setup --os-type=debian --os-version="$detected_os_version" --mariadb-server-version="$detected_mariadb_version" --skip-maxscale || error_and_exit "Could not install the $dbms repository"
	elif (( $(version_int) >= $(version_int 26.07) )); then
		# el9 has no 11.8 dnf module stream and el10 dropped dnf modularity, so MariaDB 11.8
		# is installed from the MariaDB official repository for both.
		install_mariadb_repo_setup --os-type=rhel --os-version="$detected_os_major" --mariadb-server-version="mariadb-$detected_mariadb_version" --skip-maxscale || error_and_exit "Could not install the $dbms repository"
	else
	    dnf module enable mariadb:$detected_mariadb_version -y -q || error_and_exit "Could not install the $dbms repository"
	fi
	log "INFO" "Successfully installed the $dbms repository"
	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		rm -f /etc/apt/sources.list.d/mariadb.list.old_*  > /dev/null 2>&1 || true
	else
		rm -f /etc/yum.repos.d/mariadb.repo.old_* > /dev/null 2>&1 || true
	fi
}
#========= end of function set_mariadb_repos()

#========= begin of function setup_mysql()
#
function setup_mysql() {
	log "INFO" "Install MySQL repository"
	# Centreon 26.07+ ships with MySQL 8.4 (LTS); earlier versions use MySQL 8.0.
	if (( $(version_int) >= $(version_int 26.07) )); then
		detected_mysql_version="8.4"
	else
		detected_mysql_version="8.0"
	fi

	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		if [[ "$detected_mysql_version" == "8.4" ]]; then
			# Newer mysql-apt-config knows the trixie codename and the 8.4 LTS channel;
			# preselect that channel so the install stays non-interactive.
			mysql_apt_config="mysql-apt-config_0.8.34-1_all.deb"
			curl -fJLO "https://dev.mysql.com/get/$mysql_apt_config" || error_and_exit "Failed to download $mysql_apt_config"
			echo "mysql-apt-config mysql-apt-config/select-server select mysql-8.4-lts" | debconf-set-selections || error_and_exit "Failed to preseed mysql-apt-config"
			export DEBIAN_FRONTEND="noninteractive" && $PKG_MGR install -y "./$mysql_apt_config" || error_and_exit "Failed to install $mysql_apt_config"
		else
			curl -fJLO https://dev.mysql.com/get/mysql-apt-config_0.8.29-1_all.deb || error_and_exit "Failed to download mysql-apt-config"
			export DEBIAN_FRONTEND="noninteractive" && $PKG_MGR install -y ./mysql-apt-config_0.8.29-1_all.deb || error_and_exit "Failed to install mysql-apt-config"
		fi
		# mysql-apt-config ships an expired/unrelated key; refresh the keyring (signed-by in mysql.list)
		# with the renewed B7B3B788A8D3785C key (valid to 2027) so apt can verify the repo.
		mysql_keyring=$(grep -ohm1 'signed-by=[^] ]*' /etc/apt/sources.list.d/mysql.list 2>/dev/null | cut -d= -f2) || true
		[ -z "$mysql_keyring" ] && mysql_keyring=/etc/apt/trusted.gpg.d/mysql.gpg
		# pipefail so a failed download fails here instead of writing an empty keyring
		if ! ( set -o pipefail; curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 | gpg --dearmor --yes -o "$mysql_keyring" ); then
			error_and_exit "Failed to refresh the MySQL APT signing key from https://repo.mysql.com/RPM-GPG-KEY-mysql-2025"
		fi
		$PKG_MGR -y update || error_and_exit "apt update failed after configuring the MySQL repository"
		$PKG_MGR install -y mysql-server mysql-common || error_and_exit "Could not install the $dbms server packages"
	else
		if [[ "$detected_mysql_version" == "8.4" ]]; then
			# el9 / el10 AppStream only provides MySQL 8.0, so the MySQL 8.4 LTS community
			# repository is added (its release rpm enables the 8.4 LTS repo by default).
			$PKG_MGR install -y "https://dev.mysql.com/get/mysql84-community-release-el${detected_os_major}-1.noarch.rpm" || error_and_exit "Failed to install the MySQL 8.4 community release package"
			# The release rpm's signing key expired 2025-10-22, so dnf rejects the re-signed packages. Replace it
			# with the renewed key (same fingerprint B7B3B788A8D3785C, valid to 2027): refresh the on-disk file,
			# drop the expired key from the rpm keyring (else the re-import is a no-op), then import the renewed one.
			curl -fsSL https://repo.mysql.com/RPM-GPG-KEY-mysql-2025 -o /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2023 || error_and_exit "Failed to download the MySQL RPM GPG key"
			old_mysql_key=$(rpm -q gpg-pubkey 2>/dev/null | grep -i a8d3785c || true)
			if [ -n "$old_mysql_key" ]; then rpm -e $old_mysql_key 2>/dev/null || true; fi
			rpm --import /etc/pki/rpm-gpg/RPM-GPG-KEY-mysql-2023 || error_and_exit "Failed to import the MySQL RPM GPG key"
			# install_weak_deps=False: the distro mariadb stack is only a Recommends here; without it dnf
			# pulls mariadb alongside Oracle MySQL and they collide on /usr/bin/mysql.
			$PKG_MGR install -y --setopt=install_weak_deps=False mysql-server || error_and_exit "Could not install the $dbms server packages"
		else
			$PKG_MGR install -y mysql-server mysql || error_and_exit "Could not install the $dbms server packages"
		fi
	fi
	log "INFO" "Successfully installed the $dbms repository"
	systemctl enable --now $mysql_service_name || error_and_exit "Failed to enable and start $mysql_service_name"
	if (( $(version_int) < $(version_int 24.10) )); then
		echo "default-authentication-plugin=mysql_native_password" >> $mysql_config_file
	fi
	sed -Ei 's/LimitNOFILE\s\=\s[0-9]{1,}/LimitNOFILE = 32000/' /usr/lib/systemd/system/$mysql_service_name.service || log "WARN" "Could not raise LimitNOFILE for $mysql_service_name"
	systemctl daemon-reload || true
}
#========= end of function setup_mysql()


#========= begin of function set_required_prerequisite()
# check if the target OS is compatible with Red Hat and the version is 8 or 9
# then set the required environment variables accordingly
#
function set_required_prerequisite() {
	log "INFO" "Check if the system OS is supported and set the environment variables"

	get_os_information

  case "$detected_os_release" in
	oraclelinux-release* | redhat-release* | centos-release-* | centos-linux-release* | centos-stream-release* | almalinux-release* | rocky-release*)
		case "$detected_os_version" in
		8*)
			log "INFO" "Setting specific part for v8 ($detected_os_version)"
			if (( $(version_int) >= $(version_int 26.07) )); then
				error_and_exit "Centreon $version is not supported on Red-Hat compatible v8 (el8). Please use el9, el10 or Debian 13. See https://docs.centreon.com/docs/installation/introduction for alternative installation methods."
			fi
			set_release_repo_file
			PHP_SERVICE_UNIT="php-fpm"
			HTTP_SERVICE_UNIT="httpd"
			PKG_MGR="dnf"

			case "$detected_os_release" in
			redhat-release*)
				BASE_PACKAGES=(dnf-plugins-core)
				subscription-manager repos --enable codeready-builder-for-rhel-8-x86_64-rpms || log "WARN" "Could not enable the codeready-builder repository (best-effort)"
				$PKG_MGR config-manager --set-enabled codeready-builder-for-rhel-8-rhui-rpms || true
				dnf install -y http://dl.fedoraproject.org/pub/epel/epel-release-latest-8.noarch.rpm || error_and_exit "Failed to install the EPEL release package"
				;;

			centos-release-8.[3-9]* | centos-linux-release* | centos-stream-release* | almalinux-release* | rocky-release*)
				BASE_PACKAGES=(dnf-plugins-core epel-release)
				$PKG_MGR config-manager --set-enabled powertools || true
				;;

			centos-release-8.[1-2]*)
				BASE_PACKAGES=(dnf-plugins-core epel-release)
				$PKG_MGR config-manager --set-enabled PowerTools || true
				;;

			oraclelinux-release* | enterprise-release*)
				BASE_PACKAGES=(dnf-plugins-core)
				$PKG_MGR config-manager --set-enabled ol8_codeready_builder || true
				dnf install -y http://dl.fedoraproject.org/pub/epel/epel-release-latest-8.noarch.rpm || error_and_exit "Failed to install the EPEL release package"
			;;
			esac

			if [ "$topology" == "central" ]; then
				case "$version" in
					"24.10" | "25.10")
						log "INFO" "Installing PHP 8.2 and enable it"
						$PKG_MGR module reset php -y -q || true
						$PKG_MGR module install php:8.2 -y -q || error_and_exit "Failed to install the PHP 8.2 module"
						$PKG_MGR module enable php:8.2 -y -q || error_and_exit "Failed to enable the PHP 8.2 module"
						;;
					*)
						log "INFO" "Installing PHP 8.2 from OS official repositories"
						;;
				esac
			fi
			;;

		9*)
			log "INFO" "Setting specific part for v9 ($detected_os_version)"
			set_release_repo_file
			PHP_SERVICE_UNIT="php-fpm"
			HTTP_SERVICE_UNIT="httpd"
			PKG_MGR="dnf"

			case "$detected_os_release" in
			redhat-release*)
				BASE_PACKAGES=(dnf-plugins-core)
				subscription-manager repos --enable codeready-builder-for-rhel-9-x86_64-rpms || log "WARN" "Could not enable the codeready-builder repository (best-effort)"
				$PKG_MGR config-manager --set-enabled codeready-builder-for-rhel-9-rhui-rpms || true
				dnf install -y http://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm || error_and_exit "Failed to install the EPEL release package"
				;;

			centos-release* | centos-linux-release* | centos-stream-release* | almalinux-release* | rocky-release*)
				BASE_PACKAGES=(dnf-plugins-core epel-release)
				$PKG_MGR config-manager --set-enabled crb || true
				;;

			oraclelinux-release* | enterprise-release*)
				BASE_PACKAGES=(dnf-plugins-core)
				$PKG_MGR config-manager --set-enabled ol9_codeready_builder || true
				dnf install -y http://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm || error_and_exit "Failed to install the EPEL release package"
			;;
			esac

			if [ "$topology" == "central" ]; then
				if (( $(version_int) >= $(version_int 26.07) )); then
					# PHP 8.4 is not available in the el9 OS repositories, so it is
					# provided by the remi repository.
					log "INFO" "Installing PHP 8.4 from the remi repository and enable it"
					$PKG_MGR install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm || error_and_exit "Failed to install the remi repository, required for PHP 8.4 on el9"
					$PKG_MGR module -y switch-to php:remi-8.4/common || error_and_exit "Failed to switch to the PHP 8.4 module from remi"
				elif (( $(version_int) >= $(version_int 24.10) )); then
					log "INFO" "Installing PHP 8.2 and enable it"
					$PKG_MGR module reset php -y -q || true
					$PKG_MGR module install php:8.2 -y -q || error_and_exit "Failed to install the PHP 8.2 module"
					$PKG_MGR module enable php:8.2 -y -q || error_and_exit "Failed to enable the PHP 8.2 module"
				else
					log "INFO" "Installing PHP from OS official repositories"
				fi
			fi
			;;

		10*)
			log "INFO" "Setting specific part for v10 ($detected_os_version)"
			if (( $(version_int) < $(version_int 26.07) )); then
				error_and_exit "Centreon $version is not supported on Red-Hat compatible v10 (el10). Only Centreon >= 26.07 is compatible. You chose $version"
			fi
			set_release_repo_file
			PHP_SERVICE_UNIT="php-fpm"
			HTTP_SERVICE_UNIT="httpd"
			PKG_MGR="dnf"

			case "$detected_os_release" in
			redhat-release*)
				BASE_PACKAGES=(dnf-plugins-core)
				subscription-manager repos --enable codeready-builder-for-rhel-10-x86_64-rpms || log "WARN" "Could not enable the codeready-builder repository (best-effort)"
				$PKG_MGR config-manager --set-enabled codeready-builder-for-rhel-10-rhui-rpms || true
				dnf install -y http://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm || error_and_exit "Failed to install the EPEL release package"
				;;

			centos-release* | centos-linux-release* | centos-stream-release* | almalinux-release* | rocky-release*)
				BASE_PACKAGES=(dnf-plugins-core epel-release)
				$PKG_MGR config-manager --set-enabled crb || true
				;;

			oraclelinux-release* | enterprise-release*)
				BASE_PACKAGES=(dnf-plugins-core)
				$PKG_MGR config-manager --set-enabled ol10_codeready_builder || true
				dnf install -y http://dl.fedoraproject.org/pub/epel/epel-release-latest-10.noarch.rpm || error_and_exit "Failed to install the EPEL release package"
			;;
			esac

			# On el10 there is no dnf modularity: PHP 8.4 is provided directly by the
			# OS AppStream and pulled in as a Centreon dependency. Nothing to enable here.
			if [ "$topology" == "central" ]; then
				log "INFO" "Installing PHP 8.4 from OS official repositories"
			fi
			;;

		*)
			error_and_exit "This '$script_short_name' script only supports Red-Hat compatible distribution (v8, v9 and v10) and Debian 12/13. Please check https://docs.centreon.com/docs/installation/introduction for alternative installation methods."
			;;
		esac

		log "INFO" "Installing packages ${BASE_PACKAGES[@]}"
		$PKG_MGR -q install -y ${BASE_PACKAGES[@]} || error_and_exit "Failed to install base packages: ${BASE_PACKAGES[*]}"

		log "INFO" "Updating package gnutls"
		$PKG_MGR -q update -y gnutls || log "WARN" "Could not update gnutls"

		set_centreon_repos
		if [ "$topology" == "central" ]; then
			if [[ "$dbms" == "MariaDB" ]]; then
				set_mariadb_repos
			else
				setup_mysql
			fi
			log "INFO" "Installing glibc langpack for Centreon UI translation"
			# Optional UI-translation langpacks: keep non-fatal (was masked by a '$PKG_MGR-q' typo).
			$PKG_MGR -q install -y glibc-langpack-fr glibc-langpack-es glibc-langpack-pt glibc-langpack-de > /dev/null 2>&1 || true
		fi
		;;
	debian-release*)
		log "INFO" "Setting specific part for $detected_os_release"
		HTTP_SERVICE_UNIT="apache2"
		PKG_MGR="apt -qq"
		case "$detected_os_version" in
		13)
			if (( $(version_int) < $(version_int 26.07) )); then
				error_and_exit "For Debian $detected_os_version, only Centreon >= 26.07 is compatible. You chose $version"
			fi
			# On Debian 13 (trixie), PHP 8.4 is provided by the official OS repositories.
			PHP_SERVICE_UNIT="php8.4-fpm"
			;;
		12)
			if ! [[ "$version" == "24.10" || "$version" == "25.10" ]]; then
				error_and_exit "For Debian $detected_os_version, only Centreon versions 24.10 and 25.10 are compatible. You chose $version"
			fi
			PHP_SERVICE_UNIT="php8.2-fpm"
			;;
		*)
			error_and_exit "This '$script_short_name' script only supports Red-Hat compatible distribution (v8, v9 and v10) and Debian 12/13. Please check https://docs.centreon.com/docs/installation/introduction for alternative installation methods."
			;;
		esac
		# Don't gate prerequisite install on 'apt update': a prior run may have added the not-yet-signed
		# Centreon repos, making update fail. Run it best-effort, then install wget/gnupg2/curl unconditionally
		# (a '&&' here would skip them and leave gpg absent for the key import below).
		${PKG_MGR} update || true
		base_apt_packages="lsb-release ca-certificates apt-transport-https wget gnupg2 curl"
		# software-properties-common (add-apt-repository) is kept on Debian 12 but dropped on Debian 13,
		# where the package no longer exists; this script adds repos via .list files, so it is not needed.
		if [[ "$detected_os_version" == "12" ]]; then
			base_apt_packages="$base_apt_packages software-properties-common"
		fi
		${PKG_MGR} install -y $base_apt_packages || error_and_exit "Failed to install base Debian packages: $base_apt_packages"
		repo_prefix="apt"
		# non-".10" versions are only published to the internal APT repository
		if uses_internal_repo; then
			apt_standard_repo="$repo_prefix-standard-internal"
		else
			apt_standard_repo="$repo_prefix-standard"
		fi

		# Get CPU architecture type ('|| true': absent 'Vendor ID' line just means non-ARM)
		VENDORID=$(lscpu | grep -e '^Vendor ID:' | cut -d ':' -f2 | tr -d '[:space:]') || true
		ARCH=""
		if [[ "$VENDORID" == "ARM" ]]; then
			ARCH="[ arch=all,arm64 ]"
		fi

		# Add Centreon repositories
		set_centreon_repos
		IFS=', ' read -r -a array_apt <<<"$CENTREON_REPO"
		for _repo in "${array_apt[@]}"; do
		    if (( $(version_int) < $(version_int 25.10) )); then
			    echo "deb https://packages.centreon.com/$repo_prefix-standard-$_repo/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-$_repo.list
			else
				echo "deb https://packages.centreon.com/$apt_standard_repo/ $(lsb_release -sc)-$_repo main" | tee /etc/apt/sources.list.d/centreon-$_repo.list
			fi

			SIMPLEREPO=$(echo $_repo | cut -d '-' -f2)
			echo "deb $ARCH https://packages.centreon.com/$repo_prefix-plugins-$SIMPLEREPO/ $(lsb_release -sc) main" | tee /etc/apt/sources.list.d/centreon-plugins-$SIMPLEREPO.list
		done
		# Import the Centreon APT signing key (pipefail so a failed download/dearmor is caught, not hidden).
		log "INFO" "Importing the Centreon APT signing key"
		if ! ( set -o pipefail; wget -O- https://apt-key.centreon.com | gpg --dearmor | tee /etc/apt/trusted.gpg.d/centreon.gpg > /dev/null ); then
			error_and_exit "Failed to import the Centreon APT signing key from https://apt-key.centreon.com"
		fi

		if [ "$topology" == "central" ]; then
			# On Debian, PHP comes from the OS repos (8.2 bookworm / 8.4 trixie) — no third-party repo.
			log "INFO" "Installing php from official os repositories."
			if [[ "$dbms" == "MariaDB" ]]; then
				set_mariadb_repos
			else
				setup_mysql
			fi
		else
			${PKG_MGR} update || true
		fi
		;;
	esac
}
#========= end of function set_required_prerequisite()

#========= begin of function is_systemd_present()
#
function is_systemd_present() {
	# systemd check.
	running_process=$(ps --no-headers -o comm 1)
	if [ "$running_process" == "systemd" ]; then
		has_systemd=1
		log "INFO" "Systemd is running"
	else
		has_systemd=0
		log "WARN" "Systemd is not running"
	fi
}
#========= end of function is_systemd_present()

#========= begin of function set_selinux_config()
# change SELinux config: $1 (permissive | enforcing | disabled)
#
function set_selinux_config() {

	log "INFO" "Change SELinux config to mode [$1]"

	if [ -e /etc/selinux/config ]; then
		log "WARN" "Modifying /etc/selinux/config. You must reboot your machine."

		if ! sed -i "s/^SELINUX=.*\$/SELINUX=$1/" /etc/selinux/config; then
			error_and_exit "Could not change SELinux mode. You might need to run this script as root."
		fi
	else
		log "WARN" "Cannot read /etc/selinux/config. Do nothing"
	fi

}
#========= end of function set_selinux_config()

#========= begin of function set_runtime_selinux_mode ()
# set runtime SELinux mode: $1 (permissive | enforcing)
#
function set_runtime_selinux_mode() {
	log "INFO" "Set runtime SELinux mode to [$1]"

	_current_mode=$(getenforce 2>/dev/null | tr '[:upper:]' '[:lower:]' || true)

	log "DEBUG" "Current SELinux mode is [$_current_mode]"

	shopt -s nocasematch

	if [ "$_current_mode" == "$1" ]; then
		log "DEBUG" "Current SELinux mode is already set as requested. Nothing to do"
		return
	fi

	_request_mode=0 #Default mode is permissive
	case $1 in
	permissive)
		log "DEBUG" "Change runtime mode to [permissive]"
		_request_mode=0
		;;

	enforcing)
		log "DEBUG" "Change runtime mode to [enforcing]"
		_request_mode=1
		;;
	esac

	# Capture setenforce's status once: reading $? twice (as before) tested the
	# previous '[' instead of setenforce, making the "disabled" branch dead code.
	_se_rc=0
	setenforce $_request_mode || _se_rc=$?

	if [ "$_se_rc" -eq 2 ]; then
		error_and_exit "Could not change SELinux mode. You might need to run this script as root."
	elif [ "$_se_rc" -eq 1 ]; then
		log "WARN" "Current SELinux mode is disabled. Nothing to do"
	fi

}

#========= end of function set_runtime_selinux_mode()

#========= begin of function write_db_no_tls_dropin()
# ensure the DB server accepts non-TLS connections (require_secure_transport=OFF).
# used by --tls disabled, and as a safe fallback when --tls enabled is requested on
# an OS for which DB TLS provisioning is not yet implemented.
#
function write_db_no_tls_dropin() {
	local tls_dropin
	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		if [[ "$dbms" == "MariaDB" ]]; then
			tls_dropin="/etc/mysql/mariadb.conf.d/99-centreon-tls.cnf"
		else
			tls_dropin="/etc/mysql/mysql.conf.d/99-centreon-tls.cnf"
		fi
	else
		tls_dropin="/etc/my.cnf.d/centreon-tls.cnf"
	fi

	log "INFO" "Configuring DB TLS mode [$tls]: writing $tls_dropin (require_secure_transport=OFF)"
	mkdir -p "$(dirname "$tls_dropin")"
	cat > "$tls_dropin" <<-EOF
		[mysqld]
		require_secure_transport=OFF
	EOF
}
#========= end of function write_db_no_tls_dropin()

#========= begin of function tls_cert_dir()
# directory holding the Centreon TLS CA + server cert, shared by all TLS consumers.
# On Debian the DB certs must live where MariaDB's AppArmor profile allows reads (/etc/mysql/**);
# /etc/pki is the RHEL convention.
#
function tls_cert_dir() {
	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		echo "/etc/mysql/centreon-tls"
	else
		echo "/etc/pki/centreon-tls"
	fi
}
#========= end of function tls_cert_dir()

#========= begin of function generate_db_tls_certificates()
# generate a persistent Root CA + server leaf cert for the DB (modeled on centreon-images gen_cert.sh).
# SANs cover the FQDN, localhost, 127.0.0.1 and every non-0.0.0.0 local IPv4. Paths exported via TLS_*.
#
function generate_db_tls_certificates() {
	local cert_dir
	cert_dir=$(tls_cert_dir)
	local ca_key="$cert_dir/rootCA.key"
	local ca_cert="$cert_dir/rootCA.pem"
	local srv_key="$cert_dir/server-key.pem"
	local srv_cert="$cert_dir/server.pem"
	local fqdn
	fqdn=$(hostname -f 2>/dev/null || hostname)

	# The 'openssl' CLI is not guaranteed on a minimal install (RHEL ships only openssl-libs); install on demand.
	command -v openssl >/dev/null 2>&1 || $PKG_MGR install -y openssl || error_and_exit "Failed to install openssl, required to generate DB TLS certificates"

	mkdir -p "$cert_dir"
	chmod 755 "$cert_dir"

	# Root CA — persistent: reuse across runs if already present.
	if [[ ! -f "$ca_cert" || ! -f "$ca_key" ]]; then
		log "INFO" "Generating Root CA for DB TLS"
		openssl genrsa -out "$ca_key" 4096 || error_and_exit "Failed to generate the Root CA key for DB TLS"
		chmod 400 "$ca_key"
		openssl req -x509 -new -nodes -key "$ca_key" -sha256 -days 3650 \
			-subj "/C=FR/L=Paris/O=Centreon/OU=RD/CN=Centreon DB Root CA" \
			-out "$ca_cert" || error_and_exit "Failed to generate the Root CA certificate for DB TLS"
		chmod 644 "$ca_cert"
	else
		log "INFO" "Reusing existing Root CA at $ca_cert"
	fi

	# Build the SAN list: FQDN + localhost + 127.0.0.1 + every non-0.0.0.0 IPv4 interface address.
	local ext_file csr_file ip ip_idx
	ext_file=$(mktemp)
	csr_file=$(mktemp)
	{
		echo "[ v3_req ]"
		echo "basicConstraints = CA:FALSE"
		echo "keyUsage = digitalSignature, keyEncipherment"
		echo "extendedKeyUsage = serverAuth"
		echo "subjectAltName = @alt_names"
		echo
		echo "[ alt_names ]"
		echo "DNS.1 = $fqdn"
		echo "DNS.2 = localhost"
		ip_idx=1
		echo "IP.$ip_idx = 127.0.0.1"
		for ip in $(ip -o -4 addr show 2>/dev/null | awk '{print $4}' | cut -d/ -f1 | grep -vx "0.0.0.0" | grep -vx "127.0.0.1" | sort -u || true); do
			ip_idx=$((ip_idx + 1))
			echo "IP.$ip_idx = $ip"
		done
	} > "$ext_file"

	log "INFO" "Generating DB server certificate (CN=$fqdn) with SANs for all interface IPs"
	# umask 077 so the private key is never briefly world-readable before the chmod 600 below.
	( umask 077; openssl genrsa -out "$srv_key" 2048 ) || error_and_exit "Failed to generate the DB server key"
	openssl req -new -key "$srv_key" -out "$csr_file" -subj "/C=FR/L=Paris/O=Centreon/CN=$fqdn" || error_and_exit "Failed to generate the DB server certificate request"
	openssl x509 -req -in "$csr_file" -CA "$ca_cert" -CAkey "$ca_key" -CAcreateserial \
		-out "$srv_cert" -days 825 -sha256 -extfile "$ext_file" -extensions v3_req || error_and_exit "Failed to sign the DB server certificate"
	rm -f "$ext_file" "$csr_file"

	# Cert is public (0644). The key must be readable by the DB user only: MariaDB/MySQL run as
	# 'mysql' and refuse a world-readable key.
	chmod 644 "$srv_cert"
	chown mysql:mysql "$srv_key" "$srv_cert" || error_and_exit "Failed to set ownership on the DB TLS key/cert"
	chmod 600 "$srv_key"

	TLS_CA_CERT="$ca_cert"
	TLS_SERVER_CERT="$srv_cert"
	TLS_SERVER_KEY="$srv_key"
}
#========= end of function generate_db_tls_certificates()

#========= begin of function configure_db_tls()
# configure DB TLS per --tls flag.
#  - disabled: server accepts plaintext (require_secure_transport=OFF).
#  - enabled: generate certs, trust the CA, enable server-side TLS + verified [client], stage DATABASE_SSL_*.
# Provision-only: require_secure_transport stays OFF so the plaintext web wizard works until PR #9237 ships.
#
function configure_db_tls() {
	# --tls disabled, or enabled on an OS not yet covered: just allow plaintext and return.
	if [[ "$tls" != "enabled" ]]; then
		write_db_no_tls_dropin
		return
	fi
	log "INFO" "Configuring DB TLS [enabled] for $dbms"
	generate_db_tls_certificates

	# OS-specific destinations for the trust store, server drop-in and client cnf.
	local server_dropin client_dropin
	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		cp "$TLS_CA_CERT" /usr/local/share/ca-certificates/centreon-db-ca.crt || error_and_exit "Failed to stage the DB CA certificate into the trust store"
		update-ca-certificates >/dev/null || error_and_exit "Failed to update the system CA trust store"
		if [[ "$dbms" == "MariaDB" ]]; then
			server_dropin="/etc/mysql/mariadb.conf.d/99-centreon-tls.cnf"
		else
			server_dropin="/etc/mysql/mysql.conf.d/99-centreon-tls.cnf"
		fi
		client_dropin="/etc/mysql/conf.d/centreon-tls-client.cnf"
	else
		cp "$TLS_CA_CERT" /etc/pki/ca-trust/source/anchors/centreon-db-ca.crt || error_and_exit "Failed to stage the DB CA certificate into the trust store"
		update-ca-trust extract || error_and_exit "Failed to update the system CA trust store"
		server_dropin="/etc/my.cnf.d/centreon-tls.cnf"
		client_dropin="/etc/my.cnf.d/centreon-tls-client.cnf"
	fi
	log "INFO" "Root CA installed into the system trust store"

	# Server-side TLS: offer TLS but do NOT enforce it yet (see function header).
	mkdir -p "$(dirname "$server_dropin")"
	log "INFO" "Writing DB server TLS config: $server_dropin (require_secure_transport=OFF)"
	cat > "$server_dropin" <<-EOF
		[mysqld]
		ssl_ca=$TLS_CA_CERT
		ssl_cert=$TLS_SERVER_CERT
		ssl_key=$TLS_SERVER_KEY
		require_secure_transport=OFF
	EOF

	# Client default: negotiate TLS and verify the server cert against our CA. MySQL's client (8.x) needs
	# ssl-mode=VERIFY_CA and rejects MariaDB's 'ssl-verify-server-cert', so pick the right syntax per DBMS.
	mkdir -p "$(dirname "$client_dropin")"
	log "INFO" "Writing DB client TLS config: $client_dropin"
	if [[ "$dbms" == "MySQL" ]]; then
		cat > "$client_dropin" <<-EOF
			[client]
			ssl-ca=$TLS_CA_CERT
			ssl-mode=VERIFY_CA
		EOF
	else
		cat > "$client_dropin" <<-EOF
			[client]
			ssl-ca=$TLS_CA_CERT
			ssl-verify-server-cert
		EOF
	fi

	# PHP -> DB TLS env, consumed by DatabaseTLSResolver once PR #9237 is installed (no-op before then).
	local dotenv="/usr/share/centreon/.env"
	if [[ -d /usr/share/centreon ]]; then
		if [[ -f "$dotenv" ]]; then
			sed -i '/^DATABASE_SSL_ENABLED=/d;/^DATABASE_VERIFY_SERVER_CERT=/d;/^DATABASE_CA_PATH=/d' "$dotenv"
		fi
		log "INFO" "Staging PHP DB TLS env in $dotenv"
		cat >> "$dotenv" <<-EOF
			DATABASE_SSL_ENABLED=1
			DATABASE_VERIFY_SERVER_CERT=1
			DATABASE_CA_PATH=$TLS_CA_CERT
		EOF
	fi
}
#========= end of function configure_db_tls()

#========= begin of function configure_db_tls_consumers()
# wire DB-TLS consumers whose config files only exist after the central is installed/configured.
# called late in the install flow (after the web install wizard).
#
function configure_db_tls_consumers() {
	[[ "$tls" == "enabled" ]] || return 0

	local ca_cert="$(tls_cert_dir)/rootCA.pem"

	# gorgone (Perl DBI): append ;mysql_ssl=1;mysql_ssl_ca=<CA> to each mysql DSN. Idempotent per DSN.
	local gorgone_cfg="/etc/centreon/config.d/10-database.yaml"
	if [[ -f "$gorgone_cfg" ]]; then
		log "INFO" "Patching gorgone DB DSN with mysql_ssl in $gorgone_cfg"
		sed -i "/dsn: \"mysql:/{/mysql_ssl=/! s|\(dsn: \"mysql:[^\"]*\)\"|\1;mysql_ssl=1;mysql_ssl_ca=$ca_cert\"|}" "$gorgone_cfg" || log "WARN" "Could not patch gorgone DB DSN for TLS in $gorgone_cfg"
		systemctl restart gorgoned >/dev/null 2>&1 || true
	else
		log "WARN" "gorgone DB config $gorgone_cfg not found; skipping gorgone DB TLS (run the web install wizard first)"
	fi

	# centreon-broker DB TLS is driven by Centreon's configuration (stored in DB, regenerated on export),
	# so it cannot be set reliably from this script. Surface it as a manual follow-up.
	log "WARN" "centreon-broker DB TLS must be enabled via Centreon configuration (broker config export); not set by this script"
	return 0
}
#========= end of function configure_db_tls_consumers()

#========= begin of function configure_web_tls()
# enable HTTPS on the Centreon web frontend (Apache) using the server certificate generated for the DB.

function configure_web_tls() {
	[[ "$tls" == "enabled" ]] || return 0
	[[ "$topology" == "central" ]] || return 0

	local cert_dir srv_cert srv_key template vhost is_debian=0
	cert_dir=$(tls_cert_dir)
	srv_cert="$cert_dir/server.pem"
	srv_key="$cert_dir/server-key.pem"
	template="/usr/share/centreon/examples/centreon-apache-https.conf"
	[[ "${detected_os_release}" =~ debian-release-.* ]] && is_debian=1
	if [[ $is_debian -eq 1 ]]; then
		vhost="/etc/apache2/sites-available/00-centreon-tls.conf"
	else
		vhost="/etc/httpd/conf.d/00-centreon-tls.conf"
	fi

	if [[ ! -f "$srv_cert" || ! -f "$srv_key" ]]; then
		log "WARN" "Server certificate not found ($srv_cert); skipping web TLS"
		return 0
	fi
	if [[ ! -f "$template" ]]; then
		log "WARN" "Apache HTTPS template not found ($template); skipping web TLS"
		return 0
	fi

	if [[ $is_debian -eq 1 ]]; then
		# apache2 ships mod_ssl; enable the modules the official template needs (Listen 443 comes from ports.conf).
		a2enmod ssl proxy proxy_fcgi headers deflate rewrite >/dev/null || error_and_exit "Could not enable required Apache modules"
	else
		# mod_ssl is not always present on RHEL; install on demand so SSLEngine is a known directive.
		if ! rpm -q mod_ssl >/dev/null 2>&1; then
			log "INFO" "Installing mod_ssl"
			$PKG_MGR install -y mod_ssl >/dev/null || error_and_exit "Could not install mod_ssl"
		fi
		# mod_ssl ships a default ssl.conf with a snake-oil :443 vhost pointing at a missing cert; reduce it
		# to just the Listen directive so :443 is bound and our vhost defines the actual <VirtualHost *:443>.
		if [[ -f /etc/httpd/conf.d/ssl.conf ]]; then
			echo "Listen 443 https" > /etc/httpd/conf.d/ssl.conf
		fi
	fi

	# Guard against template drift before substituting the cert paths.
	if ! grep -q '/etc/pki/tls/certs/ca.crt' "$template" || ! grep -q '/etc/pki/tls/private/ca.key' "$template"; then
		log "WARN" "Apache HTTPS template cert paths changed; skipping web TLS to avoid a broken vhost"
		return 0
	fi
	# The 00- prefix sorts before the install-step's Centreon HTTP vhost so our :80 redirect vhost becomes
	# Apache's default :80 vhost.
	mkdir -p "$(dirname "$vhost")"
	sed -e "s|/etc/pki/tls/certs/ca.crt|$srv_cert|g" \
	    -e "s|/etc/pki/tls/private/ca.key|$srv_key|g" \
	    "$template" > "$vhost"
	log "INFO" "Apache HTTPS vhost written to $vhost (HTTP :80 -> HTTPS :443 redirect enabled)"

	# On Debian the vhost must be symlinked into sites-enabled; RHEL auto-loads conf.d/*.conf.
	if [[ $is_debian -eq 1 ]]; then
		a2ensite 00-centreon-tls >/dev/null || error_and_exit "Could not enable the Centreon HTTPS site"
	fi

	# Open 443 if firewalld is active (update_firewall_config only opens http/snmp). No-op on Debian (no firewalld).
	if command -v firewall-cmd >/dev/null 2>&1 && firewall-cmd --state >/dev/null 2>&1; then
		firewall-cmd --zone=public --add-service=https --permanent >/dev/null 2>&1 || log "WARN" "Could not add firewall service https (best-effort)"
		firewall-cmd --reload >/dev/null 2>&1 || log "WARN" "Could not reload firewall rules"
	fi

	systemctl restart "$HTTP_SERVICE_UNIT" || error_and_exit "Could not restart $HTTP_SERVICE_UNIT after enabling HTTPS"
	log "INFO" "HTTPS enabled on the Centreon web frontend"
}
#========= end of function configure_web_tls()

#========= begin of function secure_dbms_setup()
# apply some secure requests
#
function secure_dbms_setup() {

	log "INFO" "Secure $dbms setup..."
	log "WARN" "We are applying some requests that will enhance your $dbms setup security"
	log "WARN" "Please consult the official documentation https://mariadb.com/kb/en/mysql_secure_installation/ for more details"
	log "WARN" "You can use mysqladmin in order to set a new password for user root"

	log "INFO" "Restarting $dbms service first"
	systemctl daemon-reload || error_and_exit "Could not reload systemd before restarting $dbms"
	# Note: SQL runs via 'if ! client ...' so a failure is handled with a clean message and never
	# reaches the ERR trap (which could otherwise expose the password from the command).
	if [[ $dbms == "MariaDB" ]]; then
		systemctl restart mariadb || error_and_exit "Could not restart $dbms service"
		log "INFO" "Executing SQL requests for $dbms"
		# MariaDB 11.x (Debian 13) no longer ships the legacy 'mysql' client symlink; prefer 'mariadb'.
		if command -v mariadb >/dev/null 2>&1; then mariadb_client="mariadb"; else mariadb_client="mysql"; fi
		if ! $mariadb_client -u root --verbose <<-EOF
			ALTER USER 'root'@'localhost' IDENTIFIED BY '${db_root_password//\'/\'\'}';
			DELETE FROM mysql.global_priv WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
			DELETE FROM mysql.global_priv WHERE User='';
			DROP DATABASE IF EXISTS test;
			DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
			FLUSH PRIVILEGES;
		EOF
		then
			error_and_exit "Could not apply the requests"
		fi
	else
		systemctl restart $mysql_service_name || error_and_exit "Could not restart $dbms service"
		log "INFO" "Executing SQL requests for $dbms"
		if (( $(version_int) >= $(version_int 24.10) )); then
			default_authentication_plugin="caching_sha2_password"
		else
			default_authentication_plugin="mysql_native_password"
		fi

		# Bootstrap root over the local socket with --ssl-mode=DISABLED: our [client] cnf (ssl-mode=VERIFY_CA)
		# would otherwise force TLS verification and break this admin step. Network clients are unaffected. Auth:
		# - MariaDB / AppStream MySQL: passwordless socket root works.
		# - Oracle MySQL 8.4: first init writes a random *expired* temp password to the server log; connect with
		#   --connect-expired-password (the ALTER below clears the expired state for the rest of the session).
		local -a mysql_root_auth=(--ssl-mode=DISABLED -u root)
		if ! mysql --ssl-mode=DISABLED -u root -e "SELECT 1" >/dev/null 2>&1; then
			local mysql_error_log mysql_temp_pw
			if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
				mysql_error_log="/var/log/mysql/error.log"
			else
				mysql_error_log="/var/log/mysqld.log"
			fi
			mysql_temp_pw=$(grep 'temporary password is generated for root@localhost' "$mysql_error_log" 2>/dev/null | tail -1 | sed -E 's/.*root@localhost: //') || true
			if [ -z "$mysql_temp_pw" ]; then
				error_and_exit "Could not authenticate to MySQL as root (no passwordless access and no temporary password in $mysql_error_log)"
			fi
			mysql_root_auth=(--ssl-mode=DISABLED --connect-expired-password -u root -p"${mysql_temp_pw}")
		fi

		if ! mysql "${mysql_root_auth[@]}" --verbose <<-EOF
			ALTER USER 'root'@'localhost' IDENTIFIED WITH '${default_authentication_plugin}' BY '${db_root_password}';
			DELETE FROM mysql.user WHERE User='';
			DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
			DROP DATABASE IF EXISTS test;
			DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
			FLUSH PRIVILEGES;
		EOF
		then
			error_and_exit "Could not apply the requests"
		fi
	fi

	log "INFO" "Successfully applied the SQL requests for enhancing your $dbms"

}
#========= end of function secure_dbms_setup()

#========= begin of function install_centreon_repo()
# install the centos-release-scl under CentOS7
# then install Centreon official repositories
#
function install_centreon_repo() {

	log "INFO" "Centreon official repositories installation..."

	if ! $PKG_MGR config-manager --add-repo $RELEASE_REPO_FILE; then
		error_and_exit "Could not install Centreon repository"
	fi
}
#========= end of function install_centreon_repo()

#========= begin of function update_firewall_config()
# add firewall configuration for newly added services
#
function update_firewall_config() {

	log "INFO" "Update firewall configuration..."
	if command -v firewall-cmd >/dev/null 2>&1; then
		if firewall-cmd --state >/dev/null 2>&1; then
			for svc in http snmp snmptrap; do
				firewall-cmd --zone=public --add-service=$svc --permanent >/dev/null 2>&1 ||
					log "WARN" "Could not add firewall service $svc (best-effort)"
			done
			for port in "5556/tcp" "5669/tcp"; do
				firewall-cmd --zone=public --add-port=$port --permanent >/dev/null 2>&1 ||
					log "WARN" "Could not add firewall port $port (best-effort)"
			done
			log "INFO" "Reloading firewall rules"
			firewall-cmd --reload || log "WARN" "Could not reload firewall rules"
		else
			log "WARN" "Firewall was not active"
		fi
	else
		log "WARN" "Firewall was not detected"
	fi
}
#========= end of function update_firewall_config()

#========= begin of function enable_new_services()
# enable newly added services to make them active after system reboot
#
function enable_new_services() {

	log "INFO" "Enable and restart services ..."
	if [ $has_systemd -eq 1 ]; then
		case $topology in
		central)
			case $dbms in
			MariaDB)
				DBMS_SERVICE_NAME=mariadb
				;;
			MySQL)
				DBMS_SERVICE_NAME=$mysql_service_name
				;;
			esac
			log "DEBUG" "On central..."
			# Best-effort: a single service failing to start must not abort the run,
			# so the final health recap can report it (see display_services_recap).
			systemctl enable "$DBMS_SERVICE_NAME" "$PHP_SERVICE_UNIT" "$HTTP_SERVICE_UNIT" snmpd snmptrapd gorgoned centreontrapd cbd centengine centreon || log "WARN" "Some services could not be enabled (see recap below)"
			systemctl restart "$DBMS_SERVICE_NAME" "$PHP_SERVICE_UNIT" "$HTTP_SERVICE_UNIT" snmpd snmptrapd || log "WARN" "Some services could not be restarted (see recap below)"
			systemctl start centreontrapd || log "WARN" "centreontrapd could not be started (see recap below)"
			;;

		poller)
			log "DEBUG" "On poller..."
			systemctl enable centreon centengine centreontrapd snmpd snmptrapd gorgoned || log "WARN" "Some services could not be enabled"
			systemctl start centreontrapd snmptrapd || log "WARN" "Some services could not be started"
			;;
		esac
	else
		log "WARN" "Systemd not detected, skipping"
	fi
}
#========= end of function enable_new_services()

#========= begin of function setup_before_installation()
# execute some tasks before installing Centreon
# - disable SELinux
# - install Centreon official repositories
function setup_before_installation() {

	set_runtime_selinux_mode "disabled"

	install_centreon_repo
}
#========= end of function setup_before_installation()

#========= begin of function urlencode()
# URL-encode a string for use in application/x-www-form-urlencoded POST data
#
function urlencode() {
	local string="$1"
	local encoded="" char hex
	local i
	for (( i=0; i<${#string}; i++ )); do
		char="${string:$i:1}"
		case "$char" in
			[a-zA-Z0-9._~-]) encoded+="$char" ;;
			*) printf -v hex '%%%02X' "'$char"; encoded+="$hex" ;;
		esac
	done
	printf '%s' "$encoded"
}
#========= end of function urlencode()

#========= begin of function api_curl()
# Single fail-hard entry point for every wizard/API curl call: aborts unless the HTTP
# status is 200/201/204 (widen per-call with API_EXTRA_OK). Sets API_HTTP_CODE; writes
# the response body to $2. Call as a plain statement, never $(api_curl ...).
function api_curl() {
	local desc="$1" body="$2"; shift 2
	local rc=0
	API_HTTP_CODE=$(curl --silent --insecure --output "$body" --write-out '%{http_code}' "$@") || rc=$?
	if [ "$rc" -ne 0 ]; then
		error_and_exit "${desc}: could not reach the Centreon API on ${central_ip} (curl exit ${rc})"
	fi
	local ok
	for ok in 200 201 204 ${API_EXTRA_OK:-}; do
		[ "$API_HTTP_CODE" = "$ok" ] && return 0
	done
	error_and_exit "${desc}: unexpected HTTP status ${API_HTTP_CODE} (response: $(head -c 400 "$body" 2>/dev/null | tr -d '\n'))"
}
#========= end of function api_curl()

#========= begin of function install_wizard_post()
# execute a post request of the install wizard (fail-hard via api_curl)
# - $1 : session cookie
# - $2 : wizard step php script
# - $3 : request body (optional)
function install_wizard_post() {
	api_curl "install wizard step ${2}" /dev/null \
		"http://${central_ip}/centreon/install/steps/process/${2}" \
		--request POST \
		--header 'Content-Type: application/x-www-form-urlencoded; charset=UTF-8' \
		--header "Cookie: ${1}" \
		--data "${3:-}"
	log "INFO" "wizard install step ${2} response -> ${API_HTTP_CODE}"
}
#========= end of function install_wizard_post()

#========= begin of function play_install_wizard()
function play_install_wizard() {
	log "INFO" "Playing install wizard"

	local enc_db_root_password enc_db_centreon_password enc_centreon_admin_password
	enc_db_root_password=$(urlencode "$db_root_password")
	enc_db_centreon_password=$(urlencode "$db_centreon_password")
	enc_centreon_admin_password=$(urlencode "$centreon_admin_password")

	sessionID=$(curl -s -v "http://${central_ip}/centreon/install/install.php" 2>&1 | grep Set-Cookie | awk '{print $3}') || true
	[ -n "$sessionID" ] || error_and_exit "Could not obtain an install-wizard session cookie from ${central_ip}"
	api_curl "install wizard stepContent" /dev/null \
		"http://${central_ip}/centreon/install/steps/step.php?action=stepContent" --header "Cookie: ${sessionID}"
	install_wizard_post ${sessionID} "process_step3.php" 'centreon_engine_stats_binary=%2Fusr%2Fsbin%2Fcentenginestats&monitoring_var_lib=%2Fvar%2Flib%2Fcentreon-engine&centreon_engine_connectors=%2Fusr%2Flib64%2Fcentreon-connector&centreon_engine_lib=%2Fusr%2Flib64%2Fcentreon-engine&centreonplugins=%2Fusr%2Flib%2Fcentreon%2Fplugins%2F'
    case $version in
    "24."*)
        install_wizard_post ${sessionID} "process_step4.php" 'centreonbroker_etc=%2Fetc%2Fcentreon-broker&centreonbroker_cbmod=%2Fusr%2Flib64%2Fnagios%2Fcbmod.so&centreonbroker_log=%2Fvar%2Flog%2Fcentreon-broker&centreonbroker_varlib=%2Fvar%2Flib%2Fcentreon-broker&centreonbroker_lib=%2Fusr%2Fshare%2Fcentreon%2Flib%2Fcentreon-broker'
    ;;
    *)
        install_wizard_post ${sessionID} "process_step4.php" 'centreonbroker_etc=%2Fetc%2Fcentreon-broker&centreonbroker_log=%2Fvar%2Flog%2Fcentreon-broker&centreonbroker_varlib=%2Fvar%2Flib%2Fcentreon-broker&centreonbroker_lib=%2Fusr%2Fshare%2Fcentreon%2Flib%2Fcentreon-broker'
    ;;
    esac
	install_wizard_post ${sessionID} "process_step5.php" "admin_password=${enc_centreon_admin_password}&confirm_password=${enc_centreon_admin_password}&firstname=${centreon_admin_firstname}&lastname=${centreon_admin_lastname}&email=${centreon_admin_email}"
	install_wizard_post ${sessionID} "process_step6.php" "address=&port=3306&root_user=root&root_password=${enc_db_root_password}&db_configuration=centreon&db_storage=centreon_storage&db_user=centreon&db_password=${enc_db_centreon_password}&db_password_confirm=${enc_db_centreon_password}"
	if [[ -v use_vault ]]; then
	  install_wizard_post ${sessionID} "process_step_vault.php" "address=${vault_address}&port=${vault_port}&role_id=${vault_role_id}&secret_id=${vault_secret_id}&root_path=${vault_root_path}"
	fi
	install_wizard_post ${sessionID} "configFileSetup.php"
	install_wizard_post ${sessionID} "installConfigurationDb.php"
	install_wizard_post ${sessionID} "installStorageDb.php"
	install_wizard_post ${sessionID} "createDbUser.php"
	install_wizard_post ${sessionID} "insertBaseConf.php"
	install_wizard_post ${sessionID} "partitionTables.php"
	install_wizard_post ${sessionID} "generationCache.php"
	INSTALLED_EXTENSIONS='modules%5B%5D=centreon-license-manager&modules%5B%5D=centreon-pp-manager&modules%5B%5D=centreon-it-edition-extensions&modules%5B%5D=centreon-autodiscovery-server'
	install_wizard_post ${sessionID} "process_step8.php" "$INSTALLED_EXTENSIONS"
	install_wizard_post ${sessionID} "process_step9.php" 'send_statistics=1'
}
#========= end of function play_install_wizard()

#========= begin of function test_api_connection()
function test_api_connection () {
	log "INFO" "Test admin password to access Centreon's API"

	local api_output
	api_output=$(mktemp)
	# Log in to Centreon API to get a token (fail-hard via api_curl: only 200/201/204 pass)
	api_curl "Centreon API login" "$api_output" \
		"${central_ip}/centreon/api/latest/login" \
		--request POST \
		--header 'Content-Type: application/json' \
		--data "{\"security\": {\"credentials\": {\"login\": \"admin\",\"password\": \"${centreon_admin_password}\"}}}"
	token=$(sed 's/.*{"token":"\(.*\)"}}/\1/g' "$api_output")
	rm -f "$api_output"
	[ -n "${token}" ] || error_and_exit "Unable to extract the API token from the login response"
	log "DEBUG" "APIv2 token: ${token}"
}
#========= end of function test_api_connection()

#========= begin of function play_update_api()
function play_update_api () {
	log "INFO" "Install jq binary"
	$PKG_MGR -q install -y jq > /dev/null 2>&1 || error_and_exit "Failed to install jq (required for the update API)"

	log "INFO" "Update Centreon using API"

	local api_body message modules module widgets widget clear_line status status_message
	local -a module_information widget_information
	api_body=$(mktemp)

	# APIv2 login -> token
	api_curl "Centreon API login" "$api_body" \
		"${central_ip}/centreon/api/latest/login" \
		--request POST \
		--header 'Content-Type: application/json' \
		--data "{\"security\": {\"credentials\": {\"login\": \"admin\",\"password\": \"${centreon_admin_password}\"}}}"
	token=$(sed 's/.*{"token":"\(.*\)"}}/\1/g' "$api_body")
	[ -n "${token}" ] || error_and_exit "Unable to extract the API token from the login response"
	log "DEBUG" "APIv2 token: ${token}"

	# Trigger the centreon-web update. 204 = done; 404 is tolerated (returned when there is
	# nothing to update on this version).
	API_EXTRA_OK="404"
	api_curl "Centreon Web update" "$api_body" \
		"${central_ip}/centreon/api/latest/platform/updates" \
		--request PATCH \
		--header "X-AUTH-TOKEN: ${token}" \
		--header 'Content-Type: application/json' \
		--data '{"components":[{"name":"centreon-web"}]}'
	API_EXTRA_OK=""
	log "INFO" "Centreon Web update completed"

	# APIv1 login -> tokenv1
	api_curl "Centreon APIv1 login" "$api_body" \
		"${central_ip}/centreon/api/index.php?action=authenticate" \
		--request POST \
		--data "username=admin&password=${centreon_admin_password}"
	tokenv1=$(cut -f2 -d":" "$api_body" | sed -e "s/\"//g" -e "s/}//" -e 's|\\||g')
	[ -n "${tokenv1}" ] || error_and_exit "Unable to extract the APIv1 token from the login response"
	log "DEBUG" "APIv1 token: ${tokenv1}"

	# Get the list of installed modules and widgets
	api_curl "Centreon module/widget list" "$api_body" \
		"${central_ip}/centreon/api/index.php?object=centreon_module&action=list" \
		--request GET \
		--header "centreon-auth-token: ${tokenv1}"
	message=$(cat "$api_body")

	# Update each module whose current version differs from the available one
	# '[]?' tolerates a valid "no modules" response (empty/missing list); a jq parse error
	# (malformed/non-JSON body) still exits non-zero and fails hard.
	modules=$(echo "${message}" | jq '.result.module.entities[]? | "\(.id)|\(.version.current)|\(.version.available)"') || error_and_exit "Failed to parse the module list from the API response (invalid JSON)"
	for module in ${modules}; do
		clear_line=$(sed -e 's/^"//' -e 's/"$//' <<< "${module}")
		IFS="|" read -a module_information <<< "${clear_line}"
		if [ "${module_information[1]}" != "${module_information[2]}" ]; then
			api_curl "update of ${module_information[0]} module" "$api_body" \
				"${central_ip}/centreon/api/index.php?object=centreon_module&action=update&id=${module_information[0]}&type=module" \
				--request POST \
				--header "centreon-auth-token: ${tokenv1}"
			status=$(jq '.status' "$api_body") || true
			status_message=$(jq '.result.message' "$api_body") || true
			if [ "${status}" = "false" ]; then
				log "WARN" "Error during update of ${module_information[0]} module: ${status_message}"
			fi
		fi
	done

	# Update each widget whose current version differs from the available one
	# see the module list above: tolerate an empty list, fail hard on malformed JSON.
	widgets=$(echo "${message}" | jq '.result.widget.entities[]? | "\(.id)|\(.version.current)|\(.version.available)"') || error_and_exit "Failed to parse the widget list from the API response (invalid JSON)"
	for widget in ${widgets}; do
		clear_line=$(sed -e 's/^"//' -e 's/"$//' <<< "${widget}")
		IFS="|" read -a widget_information <<< "${clear_line}"
		if [ "${widget_information[1]}" != "${widget_information[2]}" ]; then
			api_curl "update of ${widget_information[0]} widget" "$api_body" \
				"${central_ip}/centreon/api/index.php?object=centreon_module&action=update&id=${widget_information[0]}&type=widget" \
				--request POST \
				--header "centreon-auth-token: ${tokenv1}"
			status=$(jq '.status' "$api_body") || true
			status_message=$(jq '.result.message' "$api_body") || true
			if [ "${status}" = "false" ]; then
				log "WARN" "Error during update of ${widget_information[0]} widget: ${status_message}"
			fi
		fi
	done

	rm -f "$api_body"
	return 0
}
#========= end of function play_update_api()

#========= begin of function play_update()
function play_update() {
	if [ -z "${centreon_admin_password}" ]; then
		error_and_exit "Centreon admin password is not defined"
	fi

	play_update_api
}
#========= end of function play_update()

#========= begin of function install_central()
# install the Centreon Central
#
function install_central() {

	log "INFO" "Centreon [$topology] installation from [${CENTREON_REPO}]"

	# Pick the DBMS-specific package by $dbms: the virtual 'centreon-database' is provided only by
	# 'centreon-mariadb', so it always resolves to MariaDB (even uninstalling MySQL). 'centreon-mysql'
	# depends on mysql-server, 'centreon-mariadb' on mariadb-server.
	if [[ $dbms == "MariaDB" ]]; then
		CENTREON_DBMS_PKG="centreon-mariadb"
	else
		CENTREON_DBMS_PKG="centreon-mysql"
	fi

	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		_install_rc=0
		if (( $(version_int) >= $(version_int 24.10) && $(version_int) <= $(version_int 24.12) )); then
			$PKG_MGR install -y $CENTREON_DBMS_PKG centreon || _install_rc=$?
		else
			$PKG_MGR install -y --no-install-recommends $CENTREON_DBMS_PKG centreon || _install_rc=$?
		fi

		if [ "$_install_rc" -ne 0 ]; then
			error_and_exit "Could not install Centreon (package centreon)"
		fi
	else
		# For MySQL, disable weak deps so dnf doesn't pull the distro mariadb server (only a Recommends)
		# alongside Oracle MySQL; centreon-web's "(mariadb or mysql)" is already satisfied by Oracle's mysql
		# client, and the hard-required mariadb-connector-c (for perl-DBD-MariaDB) is unaffected.
		local db_opts=""
		[[ "$dbms" == "MySQL" ]] && db_opts="--setopt=install_weak_deps=False"
		# install core Centreon packages from enabled repo
		if ! { $PKG_MGR -q clean all --enablerepo="*" && $PKG_MGR -q install -y $db_opts $CENTREON_DBMS_PKG centreon --enablerepo="$CENTREON_REPO"; }; then
			error_and_exit "Could not install Centreon (package centreon)"
		fi
	fi

	#
	# PHP
	#
	log "INFO" "PHP configuration"
	timezone=$($PHP_BIN -r '
		$timezoneName = timezone_name_from_abbr(trim(shell_exec("date \"+%Z\"")));
		if (preg_match("/Time zone: (\S+)/", shell_exec("timedatectl"), $matches)) {
			$timezoneName = $matches[1];
		}
		if (date_default_timezone_set($timezoneName) === false) {
			$timezoneName = "UTC";
		}
		echo $timezoneName;
	' 2>/dev/null) || true
	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		# Determine the PHP version Centreon expects for this release...
		if (( $(version_int) >= $(version_int 26.07) )); then
			expected_php_version="8.4"
		elif (( $(version_int) >= $(version_int 24.10) )); then
			expected_php_version="8.2"
		else
			expected_php_version=""
		fi
		# ...then cross-check against the PHP actually installed (authoritative for the on-disk path).
		installed_php_version=$($PHP_BIN -r 'echo PHP_MAJOR_VERSION . "." . PHP_MINOR_VERSION;' 2>/dev/null) || true
		if [[ -z "$expected_php_version" ]]; then
			php_version="$installed_php_version"
		elif [[ -n "$installed_php_version" && "$installed_php_version" != "$expected_php_version" ]]; then
			log "WARN" "Centreon $version expects PHP $expected_php_version but PHP $installed_php_version is installed; using the installed version"
			php_version="$installed_php_version"
		else
			php_version="$expected_php_version"
		fi
		if [[ -z "$php_version" ]]; then
			error_and_exit "Unable to determine the PHP version to configure for Centreon $version"
		fi
		echo "date.timezone = $timezone" >> /etc/php/$php_version/mods-available/centreon.ini
	else
		echo "date.timezone = $timezone" >> $PHP_ETC/50-centreon.ini
	fi

	log "INFO" "PHP date.timezone set to [$timezone]"

	configure_db_tls

	secure_dbms_setup
}
#========= end of function install_central()

#========= begin of function install_poller()
# install the Centreon Poller
#
function install_poller() {
	log "INFO" "Poller installation from ${CENTREON_REPO}"

	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		_install_rc=0
		if (( $(version_int) >= $(version_int 24.10) && $(version_int) <= $(version_int 24.12) )); then
			$PKG_MGR install -y $CENTREON_DBMS_PKG centreon-poller || _install_rc=$?
		else
			$PKG_MGR install -y --no-install-recommends $CENTREON_DBMS_PKG centreon-poller || _install_rc=$?
		fi

		if [ "$_install_rc" -ne 0 ]; then
			error_and_exit "Could not install Centreon (package centreon)"
		fi
	else
		if ! { $PKG_MGR -q clean all --enablerepo="*" && $PKG_MGR -q install -y centreon-poller-centreon-engine --enablerepo=$CENTREON_REPO; }; then
			error_and_exit "Could not install Centreon (package centreon)"
		fi
	fi
}
#========= end of function install_poller()

#========= begin of function update_centreon_packages()
# update Centreon packages
#
function update_centreon_packages() {
	log "INFO" "Update Centreon packages using ${CENTREON_REPO}"
	if [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		$PKG_MGR upgrade centreon || error_and_exit "Could not update Centreon"
	else
		if ! { $PKG_MGR -q clean all --enablerepo="*" && $PKG_MGR -q update -y centreon\* --enablerepo=$CENTREON_REPO; }; then
			error_and_exit "Could not update Centreon"
		fi
	fi
}
#========= end of function update_centreon_packages()

#========= begin of function restart_centreon_process()
# Restart Centreon process
#
function restart_centreon_process() {
	systemctl restart centreon snmpd snmptrapd || log "WARN" "Could not restart all Centreon processes (see recap below)"
}
#========= end of function restart_centreon_process()

#========= begin of function update_after_installation()
# execute some tasks after having installed Centreon
# - update firewall config
# - enable some newly added services
#
# ## FIXME -- according to the $topology
#
function update_after_installation() {

	update_firewall_config

	enable_new_services

	if ! [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		# install Centreon SELinux packages first (as getenforce is still at 0)
		# Non-fatal: missing SELinux rules should not abort the installation.
		if ! $PKG_MGR -q install -y ${CENTREON_SELINUX_PACKAGES[@]} --enablerepo="$CENTREON_REPO"; then
			log "WARN" "Could not install Centreon SELinux packages (best-effort)"
		else
			log "INFO" "Centreon SELinux rules are installed. Please consult the documentation https://docs.centreon.com/docs/administration/secure-platform for more details."
		fi

		#then change the SELinux mode
		set_runtime_selinux_mode $selinux_mode

		set_selinux_config $selinux_mode
	fi
}
#========= end of function update_after_installation()

#========= begin of function test_password_policy()
function test_password_policy() {
	if [[ ${#centreon_admin_password} -ge 12 && "${centreon_admin_password}" == *[A-Z]* && "${centreon_admin_password}" == *[a-z]* && "${centreon_admin_password}" == *[0-9]* && "${centreon_admin_password}" == *[\!@#$%^\&*()\\[\]{}\-_+=~\`\|\:\;\"\'\<\>\,\.\/\?]* ]]; then
        log "INFO" "Password is compliant with Centreon security policy"
    else
        error_and_exit "Password is not compliant with Centreon security policy ([A-Z][a-z][0-9][\!@#$%^\&*()\\[\]{}\-_+=~\`\|\:\;\"\'\<\>\,\.\/\?]{12,})"
    fi
}
#========= end of function test_password_policy()

#========= begin of function display_services_recap()
# Print a final platform healthcheck just before the credentials are displayed.
# Topology-aware (only the services relevant to the installed role are checked) and
# rendered in the same banner style as the run summary, recorded in the log file and
# mirrored to the console. Purely informational: it tolerates inactive units (guarded
# systemctl calls) so it never aborts the script.
#
function display_services_recap() {
	local entry name unit topos active enabled state db_unit

	if [ "${has_systemd:-0}" -ne 1 ]; then
		log "WARN" "Systemd is not available: cannot report platform healthcheck"
		return 0
	fi

	# Resolve the database unit from the chosen DBMS (robust for install and update).
	if [ "$dbms" == "MariaDB" ]; then
		db_unit="mariadb"
	else
		db_unit="${mysql_service_name:-mysqld}"
	fi

	# label | systemd unit | topologies the check applies to (space-separated)
	# http and php units differ per distro (httpd/php-fpm on EL, apache2/php8.x-fpm on
	# Debian); they are resolved via the variables set in set_required_prerequisite.
	# Topologies mirror what enable_new_services actually starts: centengine runs on
	# central AND pollers; cbd is central-only (pollers do not ship/enable a cbd unit).
	local -a recap_services=(
		"database|${db_unit}|central"
		"httpd|${HTTP_SERVICE_UNIT:-httpd}|central"
		"php-fpm|${PHP_SERVICE_UNIT:-php-fpm}|central"
		"centengine|centengine|central poller"
		"gorgone|gorgoned|central poller"
		"cbd|cbd|central"
		"centreontrapd|centreontrapd|central poller"
		"snmptrapd|snmptrapd|central poller"
		"snmpd|snmpd|central poller"
	)

	local -a results=()
	for entry in "${recap_services[@]}"; do
		IFS='|' read -r name unit topos <<<"$entry"
		# Skip services that do not apply to the installed topology.
		[[ " $topos " == *" $topology "* ]] || continue
		active=$(systemctl is-active "$unit" 2>/dev/null || true)
		enabled=$(systemctl is-enabled "$unit" 2>/dev/null || true)
		if [ "$active" == "active" ]; then
			state="[OK]    "
		else
			state="[FAILED]"
		fi
		results+=("$(printf '  %s %-14s (%-13s) active=%-10s enabled=%s' \
			"$state" "$name" "$unit" "${active:-unknown}" "${enabled:-unknown}")")
	done

	# Record in the log file.
	log "INFO" "============== Centreon platform healthcheck ([$topology]) =============="
	for entry in "${results[@]}"; do
		log "INFO" "$entry"
	done
	log "INFO" "========================================================================"

	# Mirror to the console (fd 3) when output is redirected to a log file.
	if [ -n "${LOG_FILE:-}" ]; then
		{
			echo "============== Centreon platform healthcheck ([$topology]) =============="
			for entry in "${results[@]}"; do
				echo "$entry"
			done
			echo "========================================================================"
		} >&3
	fi
	return 0
}
#========= end of function display_services_recap()

#####################################################
################ MAIN SCRIPT EXECUTION ##############

if [ $EUID -ne 0 ]; then
	error_and_exit "This script must be run as root"
fi

## Process the provided arguments in line
case "$1" in

-h)
    usage
	exit 0
	;;

update)
	operation="update"
	parse_subcommand_options "$@"
	;;

install)
	operation="install"
	parse_subcommand_options "$@"
	;;

*)
	log "WARN" "No provided operation: default value [$operation] will be used"
	#usage
	operation="install"
	parse_subcommand_options "$@"
	;;

esac

# Keep a handle to the original console (fd 3) for the credentials recap and the
# start/finish/failure notices, so they remain visible even when all other output
# is redirected to the log file.
exec 3>&1

# Write all output (stdout+stderr) straight to a real log file with a plain redirect.
# Set up AFTER argument parsing so that '-h'/usage and argument errors stay on the
# console (no log file created for them), but BEFORE setup_debug_mode and password
# generation so '-D' xtrace (which would echo generated passwords) lands in the file,
# not on the console. This is deliberately NOT 'tee'/process-substitution: in
# packer/provisioning runs bash does not wait for the async 'tee' to flush on exit,
# which can truncate the log. A direct redirect is synchronous and preserves the exit
# code (no pipeline). Disable with ENV_LOG_TO_FILE=false; override path with ENV_LOG_FILE.
if [ "${ENV_LOG_TO_FILE:-true}" == "true" ]; then
	LOG_FILE=${ENV_LOG_FILE:-/var/log/centreon-unattended-$(date +%Y%m%d-%H%M%S).log}
	if mkdir -p "$(dirname "$LOG_FILE")" 2>/dev/null && : > "$LOG_FILE" 2>/dev/null; then
		chmod 600 "$LOG_FILE" 2>/dev/null || true
		echo "unattended.sh: all output is written to [$LOG_FILE] (credentials are shown on the console only)." >&3
		exec >> "$LOG_FILE" 2>&1
		log "INFO" "===== unattended.sh started, logging to [$LOG_FILE] ====="
	else
		log "WARN" "Could not create log file [$LOG_FILE]; continuing with console output only"
		LOG_FILE=""
	fi
fi

# Enable shell xtrace now if debug mode was requested (-D flag or ENV_DEBUG_MODE=true)
setup_debug_mode

# Validate the resolved TLS mode (from ENV_DB_TLS or the --tls flag) before using it.
[[ ${SUPPORTED_TLS[$tls]} ]] || error_and_exit "Unsupported TLS mode: '$tls' (expected enabled or disabled)"

# Set DBMS password from ENV or random password if not defined
if [ "$operation" == "install" ]; then
	db_root_password=${ENV_DB_ROOT_PASSWD:-"$(genpasswd "Database user: root")"}

	if [ "$wizard_autoplay" == "true" ]; then
		# Set from ENV or random Database centreon password
		db_centreon_password=${ENV_DB_CENTREON_PASSWD:-"$(genpasswd "Database user: centreon")"}
		# Generate random password if Centreon admin password is empty
		if [ -z "${centreon_admin_password}" ]; then
			centreon_admin_password=${ENV_CENTREON_ADMIN_PASSWD:-"$(genpasswd "Centreon user: admin")"}
		else
			test_password_policy
			if ! echo "User defined password set for user [Centreon user: admin] is [$centreon_admin_password]" >>$tmp_passwords_file; then
				error_and_exit "Cannot save the admin password to [$tmp_passwords_file]"
			fi
		fi
		# Set from ENV or Administrator first name
		centreon_admin_firstname=${ENV_CENTREON_ADMIN_FIRSTNAME:-"John"}
		# Set from ENV or Administrator last name
		centreon_admin_lastname=${ENV_CENTREON_ADMIN_LASTNAME:-"Doe"}
		# Set from ENV or Administrator e-mail
		centreon_admin_email=${ENV_CENTREON_ADMIN_EMAIL:-"admin@admin.tld"}
	fi
else
	if [ "$wizard_autoplay" == "true" ]; then
		if [ -z "${centreon_admin_password}" ]; then
			error_and_exit "Centreon admin password is not defined, use '-p <centreon admin password>' option"
		else
			test_api_connection
		fi
	fi
fi

## Display all configured parameters (recorded in the log file)
log "INFO" "Start to execute operation [$operation] with following configuration parameters:"
log "INFO" " topology: \t[$topology]"
log "INFO" " version: \t[$version]"
log "INFO" " repository: [$repo]"
log "INFO" " database: \t[$dbms]"
log "INFO" " TLS mode: \t[$tls]"
log "INFO" " debug mode: [$debug_mode]"
log "WARN" "It will start in [$default_timeout_in_sec] seconds. If you don't want to wait, press any key to continue or Ctrl-C to exit"

# Mirror the run summary + countdown to the real console (fd 3) so the operator sees
# what the run will use, even when all output is redirected to the log file.
# Skipped when logging to console only (LOG_FILE empty), to avoid printing twice.
if [ -n "${LOG_FILE:-}" ]; then
	{
		echo "================ unattended.sh - run summary ================"
		echo "  operation : $operation"
		echo "  topology  : $topology"
		echo "  version   : $version"
		echo "  repository: $repo"
		echo "  database  : $dbms"
		echo "  TLS mode  : $tls"
		echo "  debug mode: $debug_mode"
		echo "  log file  : $LOG_FILE"
		echo "============================================================"
		echo "Starting in $default_timeout_in_sec seconds - press any key to continue now, or Ctrl-C to abort."
	} >&3
fi

# Wait (or until a key is pressed), then confirm the input registered and announce
# what happens next, so the operator is not left staring at a silent terminal.
if pause "" $default_timeout_in_sec; then
	start_trigger="key pressed"
else
	start_trigger="${default_timeout_in_sec}s timeout elapsed"
fi
log "INFO" "Input registered ($start_trigger). Starting [$operation] of Centreon [$topology] now - configuring repositories and installing packages, this may take several minutes."
if [ -n "${LOG_FILE:-}" ]; then
	echo "Input registered ($start_trigger). Starting [$operation] of Centreon [$topology] now (this may take several minutes); follow progress in $LOG_FILE" >&3
fi

##
# Analyze system and set the variables
##
notice "Phase 1: checking prerequisites and configuring repositories"
set_required_prerequisite
##
# Check if systemd is present
##
is_systemd_present

## Start to execute
case $operation in
install)
	if ! [[ "${detected_os_release}" =~ debian-release-.* ]]; then
		setup_before_installation
	fi

	gorgone_selinux_package_name="centreon-gorgone-selinux"

	notice "Phase 2: installing Centreon [$topology] packages (this is the longest step)"
	case $topology in
	central)
		CENTREON_SELINUX_PACKAGES=(centreon-common-selinux centreon-web-selinux centreon-broker-selinux centreon-engine-selinux $gorgone_selinux_package_name centreon-plugins-selinux)
		install_central
		CENTREON_DOC_URL="https://docs.centreon.com/docs/installation/web-and-post-installation/#web-installation"
		;;

	poller)
		CENTREON_SELINUX_PACKAGES=(centreon-common-selinux centreon-broker-selinux centreon-engine-selinux $gorgone_selinux_package_name centreon-plugins-selinux)
		install_poller
		CENTREON_DOC_URL="https://docs.centreon.com/docs/monitoring/monitoring-servers/add-a-poller-to-configuration/"
		;;
	esac

	notice "Phase 3: post-install configuration (services, firewall, SELinux)"
	update_after_installation

	if [ "$topology" == "central" ] && [ "$wizard_autoplay" == "true" ]; then
		notice "Phase 4: running the Centreon installation wizard"
		# The wizard talks plain HTTP (no -L/-k); run it BEFORE enabling the HTTPS :80->:443 redirect.
		play_install_wizard
	else
		log "INFO" "Follow the steps described in Centreon documentation: $CENTREON_DOC_URL"
	fi

	# TLS post-install steps (central only): consumer DB configs + Apache HTTPS. Run AFTER the wizard.
	if [ "$topology" == "central" ]; then
		configure_db_tls_consumers
		configure_web_tls
	fi

	if [ "$topology" == "central" ] && [ "$wizard_autoplay" == "true" ]; then
		if [[ "$tls" == "enabled" ]]; then
			log "INFO" "Log in to Centreon web interface via the URL: https://$central_ip/centreon"
		else
			log "INFO" "Log in to Centreon web interface via the URL: http://$central_ip/centreon"
		fi
	fi

	log "INFO" "Centreon [$topology] successfully installed !"
	;;

update)
	case $topology in

	central)
		notice "Phase 1: updating Centreon [$topology] packages"
		update_centreon_packages
		if [ "$wizard_autoplay" == "true" ]; then
			notice "Phase 2: applying updates through the Centreon API"
			play_update
			restart_centreon_process
			log "INFO" "Log in to Centreon web interface via the URL: http://$central_ip/centreon"
		else
			CENTREON_DOC_URL="https://docs.centreon.com/docs/update/update-centreon-platform/#update-the-centreon-solution"
			log "INFO" "Follow the steps described in Centreon documentation: $CENTREON_DOC_URL"
		fi
		;;
	poller)
		CENTREON_DOC_URL=""
		notice "Phase 1: updating Centreon [$topology] packages"
		update_centreon_packages
		restart_centreon_process
		;;
	esac

	log "INFO" "Centreon [$topology] successfully updated!"
	;;

esac

# Final platform healthcheck (topology-aware), just before the credentials output.
display_services_recap

## Major change - remind it again (in case of log level is ERROR)
if [ -e $tmp_passwords_file ] && [ "$topology" == "central" ] && [ "$operation" = "install" ]; then
	# Move the tmp file to the dest file (non-fatal: the install already succeeded)
	mv $tmp_passwords_file $passwords_file || log "WARN" "Could not move $tmp_passwords_file to $passwords_file"
	# Send the credentials to the console only (fd 3), never to the log file.
	{
		echo
		echo "****** IMPORTANT ******"
		if [ "$wizard_autoplay" == "true" ]; then
			echo "As you will need passwords for users such as [root,centreon] on your $dbms database system and [admin] on your Centreon platform, random passwords are generated"
		else
			echo "As you will need a password for the user [root] on your $dbms database system, a random password is generated"
		fi
		echo "Passwords are currently saved in [$passwords_file]"
		cat $passwords_file || true
		echo
		echo "Please save them securely and then delete this file!"
		echo
	} >&3
fi
if [ -e $tmp_passwords_file ] && [ "$operation" = "update" ]; then
	rm -f $tmp_passwords_file
fi

# Final notice on the console (the rest of the run is in the log file).
if [ -n "${LOG_FILE:-}" ]; then
	echo "unattended.sh finished successfully. Full log: ${LOG_FILE}" >&3
fi

exit 0
