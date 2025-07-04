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

require_once __DIR__ . '/../../../config/centreon.config.php';
require_once __DIR__ . '/../../class/centreonSession.class.php';
require_once __DIR__ . '/../../class/centreon.class.php';
require_once __DIR__ . '/../../class/centreonDB.class.php';

CentreonSession::start();

$pearDB = new CentreonDB();

$session = $pearDB->query("SELECT * FROM `session` WHERE `session_id` = '" . session_id() . "'");
if (! $session->rowCount()) {
    exit;
}

$logos_path = '../../img/media/';

if (isset($_GET['id']) && $_GET['id'] && is_numeric($_GET['id'])) {
    $query = 'SELECT dir_name, img_path FROM view_img_dir, view_img, view_img_dir_relation vidr '
        . 'WHERE view_img_dir.dir_id = vidr.dir_dir_parent_id AND vidr.img_img_id = img_id AND img_id = :img_id';
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':img_id', $_GET['id'], PDO::PARAM_INT);
    $statement->execute();
    while ($img = $statement->fetch(PDO::FETCH_ASSOC)) {
        $imgDirName = basename($img['dir_name']);
        $imgName = basename($img['img_path']);
        $imgPath = $logos_path . $imgDirName . '/' . $imgName;
        if (! is_file($imgPath)) {
            $imgPath = _CENTREON_PATH_ . 'www/img/media/' . $imgDirName . '/' . $imgName;
        }
        if (is_file($imgPath)) {
            $mimeType = finfo_file(finfo_open(), $imgPath, FILEINFO_MIME_TYPE);
            $fileExtension = substr($imgName, strrpos($imgName, '.') + 1);
            try {
                switch ($mimeType) {
                    case 'image/jpeg':
                        /**
                         * use @ to avoid PHP Warning log and instead log a more suitable error in centreon-web.log
                         */
                        $image = @imagecreatefromjpeg($imgPath);
                        if (! $image || ! imagejpeg($image)) {
                            CentreonLog::create()->error(
                                CentreonLog::TYPE_BUSINESS_LOG,
                                'Unable to validate image, your image may be corrupted',
                                [
                                    'mime_type' => $mimeType,
                                    'filename' => $imgName,
                                    'extension' => $fileExtension,
                                ]
                            );

                            throw new Exception('Failed to create image from JPEG');
                        }
                        break;
                    case 'image/png':
                        /**
                         * use @ to avoid PHP Warning log and instead log a more suitable error in centreon-web.log
                         */
                        $image = @imagecreatefrompng($imgPath);
                        if (! $image || ! imagepng($image)) {
                            CentreonLog::create()->error(
                                CentreonLog::TYPE_BUSINESS_LOG,
                                'Unable to validate image, your image may be corrupted',
                                [
                                    'mime_type' => $mimeType,
                                    'filename' => $imgName,
                                    'extension' => $fileExtension,
                                ]
                            );

                            throw new Exception('Failed to create image from PNG');
                        }
                        break;
                    case 'image/gif':
                        /**
                         * use @ to avoid PHP Warning log and instead log a more suitable error in centreon-web.log
                         */
                        $image = @imagecreatefromgif($imgPath);
                        if (! $image || ! imagegif($image)) {
                            CentreonLog::create()->error(
                                CentreonLog::TYPE_BUSINESS_LOG,
                                'Unable to validate image, your image may be corrupted',
                                [
                                    'mime_type' => $mimeType,
                                    'filename' => $imgName,
                                    'extension' => $fileExtension,
                                ]
                            );

                            throw new Exception('Failed to create image from GIF');
                        }
                        break;
                    case 'image/svg+xml':
                        $image = file_get_contents($imgPath);
                        header('Content-Type: image/svg+xml');
                        echo $image;
                        break;
                    default:
                        throw new Exception("Unsupported image type: {$mimeType}");
                        break;
                }
            } catch (Throwable $e) {
                echo $e->getMessage();
            }
        } else {
            echo 'File not found';
        }
    }
}
