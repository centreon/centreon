<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

use enshrined\svgSanitize\Sanitizer;

require_once realpath(__DIR__ . '/../../../../../config/centreon.config.php');

require_once './class/centreonDB.class.php';
require_once './class/centreonLog.class.php';

$pearDB = new CentreonDB();
$mediaLogInstance = CentreonLog::create();

/**
 * Counters
 */
global $regCounter, $gdCounter, $fileRemoved, $dirCreated;

$sid = session_id();
if ($sid === false) {
    exit;
}

if (isset($sid)) {
    $DBRESULT = $pearDB->query("SELECT * FROM session WHERE session_id = '" . $pearDB->escape($sid) . "'");
    if ($DBRESULT->rowCount() === 0) {
        exit();
    }
}

$dir = './img/media/';

$rejectedDir = ['.' => 1, '..' => 1];

$dirCreated = 0;
$regCounter = 0;
$gdCounter = 0;

if (is_dir($dir)) {
    if ($dh = opendir($dir)) {
        while (($subdir = readdir($dh)) !== false) {
            if (! isset($rejectedDir[$subdir]) && filetype($dir . $subdir) === 'dir') {
                try {
                    $dir_id = checkDirectory($subdir, $pearDB);
                } catch (Exception $ex) {
                    $mediaLogInstance->error(
                        CentreonLog::TYPE_BUSINESS_LOG,
                        $ex->getMessage(),
                        [],
                        $ex
                    );
                    continue;
                }
                if ($dh2 = opendir($dir . $subdir)) {
                    while (($picture = readdir($dh2)) !== false) {
                        if (! isset($rejectedDir[$picture])) {
                            try {
                                checkPicture($picture, $dir . $subdir, $dir_id, $pearDB);
                            } catch (Exception $ex) {
                                $mediaLogInstance->error(
                                    CentreonLog::TYPE_BUSINESS_LOG,
                                    $ex->getMessage(),
                                    [],
                                    $ex
                                );
                            }
                        }
                    }
                    closedir($dh2);
                }
            }
        }
        closedir($dh);
    }
}

$fileRemoved = deleteOldPictures($pearDB);

// Display Stats

?>
<script type="text/javascript">
    jQuery('body').css('overflow-x', 'hidden');
    function reloadAndClose() {
        window.opener.location.reload();
        window.close();
    }
</script>
<br>
<?php
echo '<b>&nbsp;&nbsp;' . _('Media Detection') . '</b>';
?>
<br><br>
<div style="width:250px;height:50px;margin-left:5px;padding:20px;background-color:#FFFFFF;border:1px #CDCDCD solid;-moz-border-radius:4px;">
    <div style='float:left;width:270px;text-align:left;'>
        <p>
            <?php
            echo _('Bad picture alias detected :') . " {$fileRemoved}<br>";
            echo _('New directory added :') . " {$dirCreated}<br>";
            echo _('New images added :') . " {$regCounter}<br>";
            echo _('Convert gd2 -> png :') . " {$gdCounter}<br><br><br>";
            ?>
        </p>
        <br><br><br>
        <center><a href='javascript:window.opener.location.reload();javascript:window.close();'>
                <?php print _("Close"); ?>
            </a></center>
    </div>
    <br>
    <?php

    // recreates local centreon directories as defined in DB

    /**
     * Recreates local centreon directories as defined in DB.
     *
     * @param string $directory
     * @param CentreonDB $db
     *
     * @throws CentreonDbException
     * @return int Id of the directory
     */
    function checkDirectory(string $directory, CentreonDB $db): int
    {
        global $dirCreated;
        $statement = $db->prepareQuery(
            'SELECT dir_id FROM view_img_dir WHERE dir_alias = :directory'
        );
        $db->executePreparedQuery($statement, [':directory' => $directory]);
        $directoryId = $db->fetchColumn($statement);
        if ($directoryId === false) {
            $statement = $db->prepareQuery(<<<'SQL'
                INSERT INTO view_img_dir (`dir_name`, `dir_alias`)
                VALUES (:directory, :directory)
                SQL
            );
            $db->executePreparedQuery($statement, [':directory' => $directory]);
            $directoryId = $db->lastInsertId();
            @mkdir("./img/media/{$directory}");
            $dirCreated++;
        }

        return $directoryId;
    }

    /**
     * Inserts $dir_id/$picture into DB if not registered yet
     *
     * @param string $imagePath
     * @param string $directoryPath
     * @param int $directoryId
     * @param CentreonDB $pearDB
     *
     * @throws CentreonDbException
     * @return void
     */
    function checkPicture(
            string $imagePath,
            string $directoryPath,
            int $directoryId,
            CentreonDB $pearDB
    ): void {
        global $regCounter, $gdCounter;

        $mimeTypeFileExtensionConcordance = [
            "svg" => "image/svg+xml",
            "jpg" => "image/jpeg",
            "jpeg" => "image/jpeg",
            "gif" => "image/gif",
            "png" => "image/png",
            "gd2" => "",
        ];

        [$filename, $extension] = extractFileInfo($imagePath);

        if ($filename === '') {
            return;
        }

        if (! array_key_exists(strtolower($extension), $mimeTypeFileExtensionConcordance)) {
            return;
        }

        if ($extension === 'gd2' && ! is_file($filename . '.png')) {
            convertGd2ToPng($directoryPath, $imagePath);
            $gdCounter++;
        }

        $mimeType = mime_content_type($directoryPath . '/' . $imagePath);

        if (! preg_match('/^image\/(jpg|jpeg|svg\+xml|gif|png)$/', $mimeType)) {
            return;
        }

        if ($mimeType === "image/svg+xml") {
            sanitizeSvg($directoryPath, $imagePath);
        }

        $statement = $pearDB->prepareQuery(<<<'SQL'
            SELECT 1
            FROM view_img
            INNER JOIN view_img_dir_relation idr
                ON idr.img_img_id = img_id
            WHERE img_path = :img_path
                AND idr.dir_dir_parent_id = :parent_id
            SQL
        );

        $pearDB->executePreparedQuery(
            $statement,
            [
                ':img_path' => $filename . '.' . $extension,
                ':parent_id' => $directoryId,
            ]
        );

        if (! $statement->rowCount()) {
            $pearDB->beginTransaction();
            try {
                $pearDB->executePreparedQuery(
                    $pearDB->prepareQuery(
                        'INSERT INTO view_img (`img_name`, `img_path`) VALUES (:img_name, :img_path)'
                    ), [
                        ':img_name' => $filename,
                        ':img_path' => $imagePath,
                    ]
                );

                $imageId = $pearDB->lastInsertId();
                $statement = $pearDB->prepareQuery(
                    <<<'SQL'
                    INSERT INTO view_img_dir_relation (`dir_dir_parent_id`, `img_img_id`)
                    VALUES (:parent_id, :img_id)
                    SQL
                );
                $pearDB->executePreparedQuery(
                    $statement, [
                        ':parent_id' => $directoryId,
                        ':img_id' => $imageId,
                    ]
                );
                $pearDB->commit();
                $regCounter++;
            } catch (Exception $ex) {
                $pearDB->rollBack();
                throw $ex;
            }
        }
    }

    /**
     * @param string $directoryPath
     * @param string $picture
     */
    function sanitizeSvg(string $directoryPath, string $picture): void
    {
        $sanitizer = new Sanitizer();
        $svgFile = file_get_contents($directoryPath . '/' . $picture);
        $cleanSVG = $sanitizer->sanitize($svgFile);
        file_put_contents($directoryPath . '/' . $picture, $cleanSVG);
    }

    /**
     * Extracts file info from a picture
     *
     * @param string $picture
     * @return array{0: string, 1: string} [filename, extension]
     */
    function extractFileInfo(string $picture): array
    {
        $fileInfo = pathinfo($picture);
        if (! isset($fileInfo['filename'])) {
            $basenameDetails = explode('.', $fileInfo['basename']);
            $fileInfo['filename'] = $basenameDetails[0];
        }

        return [
            $fileInfo['filename'],
            $fileInfo['extension'],
        ];
    }

    function convertGd2ToPng(string $directoryPath, string $picture): void
    {
        $gdImage = imagecreatefromgd2($directoryPath . '/' . $picture);
        if (! $gdImage) {
            return;
        }
        [$filename] = extractFileInfo($picture);
        imagepng($gdImage, $directoryPath . '/' . $filename . '.png');
        imagedestroy($gdImage);
    }

    /**
     * Removes obsolete files from DB if not on filesystem.
     *
     * @param CentreonDB $pearDB
     *
     * @return int Number of files removed
     */
    function deleteOldPictures(CentreonDB $pearDB): int
    {
        $fileRemoved = 0;
        $DBRESULT = $pearDB->query(
            'SELECT img_id, img_path, dir_alias FROM view_img vi, '
            . 'view_img_dir vid, view_img_dir_relation vidr '
            . 'WHERE vidr.img_img_id = vi.img_id AND vid.dir_id = vidr.dir_dir_parent_id'
        );
        $statement = $pearDB->prepare('DELETE FROM view_img WHERE img_id = :img_id');
        while ($row2 = $DBRESULT->fetchRow()) {
            if (! file_exists('./img/media/' . $row2['dir_alias'] . '/' . $row2['img_path'])) {
                $statement->bindValue(':img_id', (int) $row2['img_id'], PDO::PARAM_INT);
                $statement->execute();
                $fileRemoved++;
            }
        }
        $DBRESULT->closeCursor();

        return $fileRemoved;
    }

    ?>
</div>
