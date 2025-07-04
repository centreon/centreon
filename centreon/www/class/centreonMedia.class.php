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

/**
 * Class
 *
 * @class CentreonMedia
 * @description Class used for managing images
 */
class CentreonMedia
{
    public const CENTREON_MEDIA_PATH = __DIR__ . '/../img/media/';

    /** @var CentreonDB */
    protected $db;

    /** @var array */
    protected $filenames = [];

    /** @var string */
    protected $mediadirectoryname = '';

    /**
     * CentreonMedia constructor
     *
     * @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
    }

    /**
     * Get media directory path
     *
     * @throws Exception
     * @return string
     */
    public function getMediaDirectory()
    {
        if (empty($this->mediadirectoryname)) {
            return self::CENTREON_MEDIA_PATH;
        }

        return $this->mediadirectoryname;
    }

    /**
     * Get media directory path
     *
     * @param mixed $dirname
     * @throws Exception
     * @return void
     */
    public function setMediaDirectory($dirname): void
    {
        $this->mediadirectoryname = $dirname;
    }

    /**
     * Returns ID of target directory
     *
     * @param string $dirName
     *
     * @throws PDOException
     * @return int|null
     */
    public function getDirectoryId($dirName): ?int
    {
        $dirName = $this->sanitizePath($dirName);

        $statement = $this->db->prepare(
            'SELECT dir_id FROM view_img_dir WHERE dir_name = :dirName LIMIT 1'
        );
        $statement->bindValue(':dirName', $dirName, PDO::PARAM_STR);
        $statement->execute();

        $dirId = null;
        if ($row = $statement->fetch()) {
            $dirId = (int) $row['dir_id'];
        }

        return $dirId;
    }

    /**
     * Returns name of target directory
     *
     * @param int $directoryId
     *
     * @throws PDOException
     * @return string
     */
    public function getDirectoryName($directoryId)
    {
        $query = 'SELECT dir_name FROM view_img_dir WHERE dir_id = ' . $directoryId . ' LIMIT 1';

        $result = $this->db->query($query);

        $directoryName = null;
        if ($result->rowCount()) {
            $row = $result->fetchRow();
            $directoryName = $row['dir_name'];
        }

        return $directoryName;
    }

    /**
     * Add media directory
     *
     * @param string $dirName
     * @param string $dirAlias
     *
     * @throws Exception
     * @return int
     */
    public function addDirectory($dirName, $dirAlias = null)
    {
        $dirName = $this->sanitizePath($dirName);

        if (is_null($dirAlias)) {
            $dirAlias = $dirName;
        }

        try {
            $statement = $this->db->prepare(
                'INSERT INTO `view_img_dir` (dir_name, dir_alias)
                SELECT :dirName, :dirAlias FROM DUAL
                WHERE NOT EXISTS (
                    SELECT dir_id FROM `view_img_dir`
                    WHERE `dir_name` = :dirName
                    AND `dir_alias` = :dirAlias
                    LIMIT 1
                )'
            );

            $statement->bindValue(':dirName', $dirName, PDO::PARAM_STR);
            $statement->bindValue(':dirAlias', $dirAlias, PDO::PARAM_STR);
            $statement->execute();
        } catch (PDOException $e) {
            throw new Exception('Error while creating directory ' . $dirName, (int) $e->getCode(), $e);
        }

        $this->createDirectory($dirName);

        return $this->getDirectoryId($dirName);
    }

    /**
     * Add directory
     *
     * @param string $dirname
     *
     * @throws Exception
     */
    private function createDirectory($dirname): void
    {
        $mediaDirectory = $this->getMediaDirectory();

        $fullPath = $mediaDirectory . '/' . basename($dirname);

        // Create directory and nested folder structure
        if (! is_dir($fullPath)) {
            @mkdir($fullPath, 0755, true);
            if (! is_dir($fullPath)) {
                error_log('Impossible to create directory ' . $fullPath);
            }
        } elseif (fileperms($fullPath) !== 0755) {
            chmod($fullPath, 0755);
        }
    }

    /**
     * Returns ID of target Image
     *
     * @param string $imagename
     * @param string|null $dirname
     *
     * @throws PDOException
     * @return mixed
     */
    public function getImageId($imagename, $dirname = null)
    {
        if (! isset($dirname)) {
            $tab = preg_split("/\//", $imagename);
            $dirname = $tab[0] ?? null;
            $imagename = $tab[1] ?? null;
        }

        if (! isset($imagename) || ! isset($dirname)) {
            return null;
        }

        $query = 'SELECT img.img_id '
            . 'FROM view_img_dir dir, view_img_dir_relation rel, view_img img '
            . 'WHERE dir.dir_id = rel.dir_dir_parent_id '
            . 'AND rel.img_img_id = img.img_id '
            . "AND img.img_path = '" . $imagename . "' "
            . "AND dir.dir_name = '" . $dirname . "' "
            . 'LIMIT 1';
        $RES = $this->db->query($query);
        $img_id = null;
        if ($RES->rowCount()) {
            $row = $RES->fetchRow();
            $img_id = $row['img_id'];
        }

        return $img_id;
    }

    /**
     * Returns the filename from a given id
     *
     * @param int|null $imgId
     *
     * @throws PDOException
     * @return string
     */
    public function getFilename($imgId = null)
    {
        if (! isset($imgId)) {
            return '';
        }
        if (count($this->filenames)) {
            return $this->filenames[$imgId] ?? '';
        }
        $query = 'SELECT img_id, img_path, dir_alias
	    		  FROM view_img vi, view_img_dir vid, view_img_dir_relation vidr
	    		  WHERE vidr.img_img_id = vi.img_id
	    		  AND vid.dir_id = vidr.dir_dir_parent_id';
        $res = $this->db->query($query);
        $this->filenames[0] = 0;
        while ($row = $res->fetchRow()) {
            $this->filenames[$row['img_id']] = $row['dir_alias'] . '/' . $row['img_path'];
        }

        return $this->filenames[$imgId] ?? '';
    }

    /**
     * Extract files from archive file and returns filenames
     *
     * @param string $archiveFile
     *
     * @throws Exception
     * @return array
     */
    public static function getFilesFromArchive($archiveFile)
    {
        $fileName = basename($archiveFile);
        $position = strrpos($fileName, '.');
        if (false === $position) {
            throw new Exception('Missing extension');
        }
        $extension = substr($fileName, ($position + 1));
        $files = [];
        $allowedExt = ['zip', 'tar', 'gz', 'tgzip', 'tgz', 'bz', 'tbzip', 'tbz', 'bzip', 'bz2', 'tbzip2', 'tbz2', 'bzip2'];
        if (! in_array(strtolower($extension), $allowedExt)) {
            throw new Exception('Unknown extension');
        }

        if (strtolower($extension) == 'zip') {
            $archiveObj = new ZipArchive();
            $res = $archiveObj->open($archiveFile);

            if ($res !== true) {
                throw new Exception('Could not read archive');
            }

            for ($i = 0; $i < $archiveObj->numFiles; $i++) {
                $files[] = $archiveObj->statIndex($i)['name'];
            }
        } else {
            $archiveObj = new PharData($archiveFile, Phar::KEY_AS_FILENAME);

            foreach ($archiveObj as $file) {
                $files[] = $file->getFilename();
            }
        }

        if ($files === []) {
            throw new Exception('Archive file is empty');
        }

        if (strtolower($extension) == 'zip') {
            $archiveObj->extractTo(dirname($archiveFile), $files);
            $archiveObj->close();
        } elseif (false === $archiveObj->extractTo(dirname($archiveFile), $files)) {
            throw new Exception('Could not extract files');
        }

        return $files;
    }

    /**
     * @param string $path
     *
     * @return string
     */
    private function sanitizePath($path)
    {
        $cleanstr = htmlentities($path, ENT_QUOTES, 'UTF-8');
        $cleanstr = str_replace('/', '_', $cleanstr);

        return str_replace('\\', '_', $cleanstr);
    }

    /**
     * @param array $parameters
     * @param string|null $binary
     *
     * @throws Exception
     * @return mixed
     */
    public function addImage($parameters, $binary = null)
    {
        $imageId = null;

        if (! isset($parameters['img_name']) || ! isset($parameters['img_path']) || ! isset($parameters['dir_name'])) {
            throw new Exception('Cannot add media : missing parameters');
        }

        if (! isset($parameters['img_comment'])) {
            $parameters['img_comment'] = '';
        }

        $directoryName = $this->sanitizePath($parameters['dir_name']);
        $directoryId = $this->addDirectory($directoryName);

        $imageName = htmlentities($parameters['img_name'], ENT_QUOTES, 'UTF-8');
        $imagePath = htmlentities($parameters['img_path'], ENT_QUOTES, 'UTF-8');
        $imageComment = htmlentities($parameters['img_comment'], ENT_QUOTES, 'UTF-8');

        // Check if image already exists
        $query = 'SELECT vidr.vidr_id '
            . 'FROM view_img vi, view_img_dir vid, view_img_dir_relation vidr '
            . 'WHERE vi.img_id = vidr.img_img_id '
            . 'AND vid.dir_id = vidr.dir_dir_parent_id '
            . 'AND vi.img_name = "' . $imageName . '" '
            . 'AND vid.dir_name = "' . $directoryName . '" ';

        $result = $this->db->query($query);

        if (! $result->rowCount()) {
            // Insert image in database
            $query = 'INSERT INTO view_img '
                . '(img_name, img_path, img_comment) '
                . 'VALUES ( '
                . '"' . $imageName . '", '
                . '"' . $imagePath . '", '
                . '"' . $imageComment . '" '
                . ') ';

            try {
                $result = $this->db->query($query);
            } catch (PDOException $e) {
                throw new Exception('Error while creating image ' . $imageName);
            }

            // Get image id
            $query = 'SELECT MAX(img_id) AS img_id '
                . 'FROM view_img '
                . 'WHERE img_name = "' . $imageName . '" '
                . 'AND img_path = "' . $imagePath . '" ';
            $error = false;
            try {
                $result = $this->db->query($query);
            } catch (PDOException $e) {
                $error = true;
            }
            if ($error || ! $result->rowCount()) {
                throw new Exception('Error while creating image ' . $imageName);
            }
            $row = $result->fetchRow();
            $imageId = $row['img_id'];

            // Insert relation between directory and image
            $statement = $this->db->prepare('INSERT INTO view_img_dir_relation (dir_dir_parent_id, img_img_id) '
                . 'VALUES (:dirId, :imgId) ');
            $statement->bindValue(':dirId', (int) $directoryId, PDO::PARAM_INT);
            $statement->bindValue(':imgId', (int) $imageId, PDO::PARAM_INT);
            try {
                $statement->execute();
            } catch (PDOException $e) {
                throw new Exception('Error while inserting relation between' . $imageName . ' and ' . $directoryName);
            }
        } else {
            $imageId = $this->getImageId($imageName, $directoryName);
        }

        // Create binary file if specified
        if (! is_null($binary)) {
            $directoryPath = $this->getMediaDirectory() . '/' . $directoryName . '/';
            $this->createImage($directoryPath, $imagePath, $binary);
        }

        return $imageId;
    }

    /**
     * @param string $directoryPath
     * @param string $imagePath
     * @param string $binary
     */
    private function createImage($directoryPath, $imagePath, $binary): void
    {
        $fullPath = $directoryPath . '/' . $imagePath;
        $decodedBinary = base64_decode($binary);

        if (! file_exists($fullPath)) {
            file_put_contents($fullPath, $decodedBinary);
        }
    }
}
