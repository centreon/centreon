#!/bin/bash

mysql -hdb -uroot -pcentreon centreon -e "UPDATE options SET options.value = 1 WHERE options.key = 'inheritance_mode'"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('ppm', 'ppm', 'ppm directory')"