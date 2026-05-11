LANG=C
LC_CTYPE=C

function exitMain() {
  logInfo Exit the program
  closeLog
}

trap exitMain EXIT

if ! mkdir -p "$(dirname "${LOG_FILE}")"; then
  consoleError Cannot create the directory for log file "($(dirname "${LOG_FILE}"))".
fi

if ! type curl >/dev/null 2>&1; then
  consoleError The binary curl must be installed.
  exit 1
fi

initLog "${LOG_FILE}"

subcommand=$(cmdParse $*)
eval ${subcommand}
