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

use Pimple\Container;

/**
 * Class
 *
 * @class CentreonImageManager
 */
class CentreonImageManager extends centreonFileManager
{
    /** @var string[] */
    protected $legalExtensions = ['jpg', 'jpeg', 'png', 'gif', 'svg'];

    /** @var int */
    protected $legalSize = 2000000;

    /** @var mixed */
    protected $dbConfig;

    /**
     * CentreonImageManager constructor
     *
     * @param Container $dependencyInjector
     * @param $rawFile
     * @param $basePath
     * @param $destinationDir
     * @param string $comment
     */
    public function __construct(
        Container $dependencyInjector,
        $rawFile,
        $basePath,
        $destinationDir,
        $comment = ''
    ) {
        parent::__construct($dependencyInjector, $rawFile, $basePath, $destinationDir, $comment);
        $this->dbConfig = $this->dependencyInjector['configuration_db'];
    }

    /**
     * @param bool $insert
     * @return array|bool
     */
    public function upload($insert = true)
    {
        $parentUpload = parent::upload();

        if ($parentUpload) {
            if ($insert) {
                $img_ids[] = $this->insertImg();

                return $img_ids;
            }
        } else {
            return false;
        }
    }

    /**
     * Upload file from Temp Directory
     *
     * @param string $tempDirectory
     * @param bool $insert
     *
     * @return array|bool
     */
    public function uploadFromDirectory(string $tempDirectory, $insert = true)
    {
        $tempFullPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $tempDirectory;
        if (! parent::fileExist()) {
            $this->moveImage(
                $tempFullPath . DIRECTORY_SEPARATOR . $this->rawFile['tmp_name'],
                $this->destinationPath . DIRECTORY_SEPARATOR . $this->rawFile['name']
            );
            if ($insert) {
                $img_ids[] = $this->insertImg();

                return $img_ids;
            }
        } else {
            return false;
        }
    }

    /**
     * @param string|int $imgId
     * @param string $imgName
     *
     * @return bool
     */
    public function update($imgId, $imgName)
    {
        if (! $imgId || empty($imgName)) {
            return false;
        }

        $stmt = $this->dbConfig->prepare(
            'SELECT dir_id, dir_alias, img_path, img_comment '
            . 'FROM view_img, view_img_dir, view_img_dir_relation '
            . 'WHERE img_id = :imgId AND img_id = img_img_id '
            . 'AND dir_dir_parent_id = dir_id'
        );
        $stmt->bindParam(':imgId', $imgId);
        $stmt->execute();

        $img_info = $stmt->fetch();

        // update if new file
        if (! empty($this->originalFile) && ! empty($this->tmpFile)) {
            $this->deleteImg($this->mediaPath . $img_info['dir_alias'] . '/' . $img_info['img_path']);
            $this->upload(false);

            $stmt = $this->dbConfig->prepare(
                'UPDATE view_img SET img_path  = :path WHERE img_id = :imgId'
            );
            $stmt->bindParam(':path', $this->newFile);
            $stmt->bindParam(':imgId', $imgId);
            $stmt->execute();
        }

        // update image info
        $stmt = $this->dbConfig->prepare(
            'UPDATE view_img SET img_name  = :imgName, '
            . 'img_comment = :imgComment WHERE img_id = :imgId'
        );
        $stmt->bindParam(':imgName', $this->secureName($imgName));
        $stmt->bindParam(':imgComment', $this->comment);
        $stmt->bindParam(':imgId', $imgId);
        $stmt->execute();

        // check directory
        if (! ($dirId = $this->checkDirectoryExistence())) {
            $dirId = $this->insertDirectory();
        }
        // Create directory if not exist
        if ($img_info['dir_alias'] != $this->destinationDir) {
            $img_info['img_path'] = basename($img_info['img_path']);
            $img_info['dir_alias'] = basename($img_info['dir_alias']);
            $old = $this->mediaPath . $img_info['dir_alias'] . '/' . $img_info['img_path'];
            $new = $this->mediaPath . $this->destinationDir . '/' . $img_info['img_path'];
            $this->moveImage($old, $new);
        }

        // update relation
        $stmt = $this->dbConfig->prepare(
            'UPDATE view_img_dir_relation SET dir_dir_parent_id  = :dirId '
            . 'WHERE img_img_id = :imgId'
        );
        $stmt->bindParam(':dirId', $dirId);
        $stmt->bindParam(':imgId', $imgId);
        $stmt->execute();

        return true;
    }

    /**
     * @param string $fullPath
     *
     * @return void
     */
    protected function deleteImg($fullPath)
    {
        unlink($fullPath);
    }

    /**
     * @return int
     */
    protected function checkDirectoryExistence()
    {
        $dirId = 0;
        $query = 'SELECT dir_name, dir_id FROM view_img_dir WHERE dir_name = :dirName';
        $stmt = $this->dbConfig->prepare($query);
        $stmt->bindParam(':dirName', $this->destinationDir);
        $stmt->execute();

        if ($stmt->rowCount() >= 1) {
            $dir = $stmt->fetch();
            $dirId = $dir['dir_id'];
        }

        return $dirId;
    }

    /**
     * @return mixed
     */
    protected function insertDirectory()
    {
        touch($this->destinationPath . '/index.html');

        $stmt = $this->dbConfig->prepare(
            'INSERT INTO view_img_dir (dir_name, dir_alias) '
            . 'VALUES (:dirName, :dirAlias)'
        );
        $stmt->bindParam(':dirName', $this->destinationDir, PDO::PARAM_STR);
        $stmt->bindParam(':dirAlias', $this->destinationDir, PDO::PARAM_STR);
        if ($stmt->execute()) {
            return $this->dbConfig->lastInsertId();
        }

        return null;
    }

    /**
     * @param $dirId
     *
     * @return void
     */
    protected function updateDirectory($dirId)
    {
        $query = 'UPDATE view_img_dir SET dir_name = :dirName, dir_alias = :dirAlias WHERE dir_id = :dirId';
        $stmt = $this->dbConfig->prepare($query);
        $stmt->bindParam(':dirName', $this->destinationDir, PDO::PARAM_STR);
        $stmt->bindParam(':dirAlias', $this->destinationDir, PDO::PARAM_STR);
        $stmt->bindParam(':dirId', $dirId, PDO::PARAM_INT);
        $stmt->execute();
    }

    /**
     * @return mixed
     */
    protected function insertImg()
    {
        if (! ($dirId = $this->checkDirectoryExistence())) {
            $dirId = $this->insertDirectory();
        }

        $stmt = $this->dbConfig->prepare(
            'INSERT INTO view_img (img_name, img_path, img_comment) '
            . 'VALUES (:imgName, :imgPath, :dirComment)'
        );
        $stmt->bindParam(':imgName', $this->fileName, PDO::PARAM_STR);
        $stmt->bindParam(':imgPath', $this->newFile, PDO::PARAM_STR);
        $stmt->bindParam(':dirComment', $this->comment, PDO::PARAM_STR);
        $stmt->execute();
        $imgId = $this->dbConfig->lastInsertId();

        $stmt = $this->dbConfig->prepare(
            'INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id) '
            . 'VALUES (:dirId, :imgId)'
        );
        $stmt->bindParam(':dirId', $dirId, PDO::PARAM_INT);
        $stmt->bindParam(':imgId', $imgId, PDO::PARAM_INT);
        $stmt->execute();
        $stmt->closeCursor();

        return $imgId;
    }

    /**
     * @param string $old
     * @param string $new
     */
    protected function moveImage($old, $new)
    {
        copy($old, $new);
        $this->deleteImg($old);
    }
}
