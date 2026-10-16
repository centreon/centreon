#!/bin/bash
#
# 16-tls-gorgone.sh — intentionally a no-op on trixie: DBD::mysql 4.053 on
# libmariadb 3.x cannot enable SSL, so gorgone keeps its plaintext DSN here
# (the DB does not enforce TLS). Follow-up: gorgone DBD::MariaDB support.
return 0 2>/dev/null || exit 0
