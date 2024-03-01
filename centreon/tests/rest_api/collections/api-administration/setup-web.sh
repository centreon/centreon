#!/bin/bash

mysql -hdb -uroot -pcentreon centreon -e "UPDATE options SET options.value = 1 WHERE options.key = 'inheritance_mode'"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('ppm', 'ppm', 'ppm directory')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Admin-Potter.png', 'Admin-Potter.png', 'Admin-Potter test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Admin-Potter'
"
