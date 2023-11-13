find .          \
        -type f \
        -exec grep -qE '(@CENTREON_ETC@)|(@BIN_DIR@)|(CENTREON_DIR)|(CENTREON_LOG)|(CENTREON_VARLIB)|(MODULE_NAME)|(DB_CENTSTORAGE)' {} ';'   \
        -exec sed -i -e "s|@CENTREON_ETC@|${centreon_etc}|g" \
                          -e "s|@BIN_DIR@|/usr/bin|g" \
                          -e "s|@CENTREON_DIR@|${centreon_dir}|g" \
                          -e "s|@CENTREON_LOG@|${centreon_log}|g" \
                          -e "s|@CENTREON_VARLIB@|${centreon_varlib}|g" \
                          -e "s|@MODULE_NAME@|centreon-awie|g" \
			  -e "s|@DB_CENTSTORAGE@|centreon_storage|g" \
                       {} ';'
