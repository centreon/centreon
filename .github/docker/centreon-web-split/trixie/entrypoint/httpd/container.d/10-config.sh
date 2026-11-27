#!/bin/sh
set -e

# Empty (compose/chart passing an unset value) = the image default.
export HTTPD_PROXY_TIMEOUT="${HTTPD_PROXY_TIMEOUT:-300}"

# Restricted to these variables on purpose: the template also contains
# literal Apache placeholders (${base_uri}, ${install_dir}) and regex
# backreferences ($1) that must survive this substitution untouched.
envsubst '${HTTPD_SERVER_NAME} ${PHP_FPM_UPSTREAM} ${GORGONE_WSS_UPSTREAM} ${HTTPD_PROXY_TIMEOUT}' \
  < /usr/local/apache2/conf/extra/centreon.conf.template \
  > /usr/local/apache2/conf/extra/centreon.conf
