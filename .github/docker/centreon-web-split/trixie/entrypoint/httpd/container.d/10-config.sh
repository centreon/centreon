#!/bin/sh
set -e

# Restricted to these three variables on purpose: the template also contains
# literal Apache placeholders (${base_uri}, ${install_dir}) and regex
# backreferences ($1) that must survive this substitution untouched.
envsubst '${HTTPD_SERVER_NAME} ${PHP_FPM_UPSTREAM} ${GORGONE_WSS_UPSTREAM}' \
  < /usr/local/apache2/conf/extra/centreon.conf.template \
  > /usr/local/apache2/conf/extra/centreon.conf
