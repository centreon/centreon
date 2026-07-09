#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
IMAGES_DIR="$SCRIPT_DIR/images/quality-assurance"
MEDIA_DIR="/usr/share/centreon/www/img/media/quality-assurance"

echo "==> Copying images to media directory"
mkdir -p "$MEDIA_DIR"
cp "$IMAGES_DIR"/*.png "$MEDIA_DIR"/
cp "$IMAGES_DIR"/*.jpg "$MEDIA_DIR"/
if id apache &>/dev/null; then
  chown -R apache:apache "$MEDIA_DIR"
else
  chown -R www-data:www-data "$MEDIA_DIR"
fi

echo "==> Inserting image records in database"
mysql -hdb -uroot -pcentreon centreon -e "
  INSERT IGNORE INTO view_img_dir (dir_name, dir_alias, dir_comment)
    VALUES ('quality-assurance', 'quality-assurance', 'QA test images');
  INSERT IGNORE INTO view_img (img_name, img_path, img_comment)
    VALUES
      ('icon-assurance-qualite.png',  'icon-assurance-qualite.png',  'QA icon'),
      ('wallpaper-qa-standard.jpg',   'wallpaper-qa-standard.jpg',   'QA wallpaper'),
      ('wallpaper-qa-standard-2.jpg', 'wallpaper-qa-standard-2.jpg', 'QA wallpaper 2');
  INSERT IGNORE INTO view_img_dir_relation (dir_dir_parent_id, img_img_id)
    SELECT vid.dir_id, vi.img_id
    FROM view_img_dir vid, view_img vi
    WHERE vid.dir_name = 'quality-assurance'
      AND vi.img_name IN ('icon-assurance-qualite.png', 'wallpaper-qa-standard.jpg', 'wallpaper-qa-standard-2.jpg');
"
