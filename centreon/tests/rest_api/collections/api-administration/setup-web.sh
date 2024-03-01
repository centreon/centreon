#!/bin/bash

mysql -hdb -uroot -pcentreon centreon -e "UPDATE options SET options.value = 1 WHERE options.key = 'inheritance_mode'"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('test', 'test', 'my test')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('centreon', 'centreon.png', 'centreon test image')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id) VALUES (SELECT id FROM view_img_dir WHERE dir_name = 'test' LIMIT 1, SELECT id FROM view_img WHERE img_name = 'centreon' LIMIT 1)"
