#!/bin/bash

mysql -hdb -uroot -pcentreon centreon -e "UPDATE options SET options.value = 1 WHERE options.key = 'inheritance_mode'"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('ppm', 'ppm', 'ppm directory')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Admin-Potter.png', 'Admin-Potter.png', 'Admin-Potter test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Admin-Potter.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Salameche.png', 'Salameche.png', 'Salameche test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Salameche.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Reptincel.png', 'Reptincel.png', 'Reptincel test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Reptincel.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Dracaufeu.png', 'Dracaufeu.png', 'Dracaufeu test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Dracaufeu.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('ppm', 'ppm', 'ppm directory')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Carapuce.png', 'Carapuce.png', 'Carapuce test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Carapuce.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('ppm', 'ppm', 'ppm directory')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Carabaffe.png', 'Carabaffe.png', 'Carabaffe test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Carabaffe.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img_dir (dir_name, dir_alias, dir_comment) VALUES ('ppm', 'ppm', 'ppm directory')"
mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Tortank.png', 'Tortank.png', 'Tortank test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Tortank.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Bulbizarre.png', 'Bulbizarre.png', 'Bulbizarre test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Bulbizarre.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Herbizarre.png', 'Herbizarre.png', 'Herbizarre test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Herbizarre.png'
"

mysql -hdb -uroot -pcentreon centreon -e "INSERT INTO view_img (img_name, img_path, img_comment) VALUES ('Florizarre.png', 'Florizarre.png', 'Florizarre test image')"
mysql -hdb -uroot -pcentreon centreon -e "
INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
SELECT vid.dir_id, vi.img_id FROM view_img_dir vid, view_img vi
WHERE vid.dir_name = 'ppm'
AND vi.img_name = 'Florizarre.png'
"