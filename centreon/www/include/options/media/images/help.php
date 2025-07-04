<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

$help = [];
$help['img_name'] = dgettext('help', 'Enter a new name for the image.');
$help['img_dir'] = dgettext(
    'help',
    'Enter an already existing or a new directory to add the uploads '
    . 'to it. A non-existent directory name will be created first.'
);
$help['img_file'] = dgettext(
    'help',
    'Select a local file to upload. You can upload jpg, png, gif and '
    . 'gd2 files. Multiple images can be uploaded together inside '
    . 'archives like zip, tar, tar.gz or tar.bz2.'
);

/**
 * formDirectory.ihtml
 */
$help['tip_destination_directory'] = dgettext('help', 'Destination directory.');
$help['tip_images'] = dgettext('help', 'Images.');
