<?php

/**
 * Copyright 2021 Centreon
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class ZipAndDownload
{
    /**
     * ZipAndDownload constructor.
     * @param $fileNames
     * @param string $filePath
     * @param string $fileExtension
     */
    public function __construct($fileNames, $filePath = '/tmp', $fileExtension = '.txt')
    {
        $archivePath = $filePath . '/' . $fileNames . '.zip';
        $filePath .= '/' . $fileNames . $fileExtension;
        $zip = new ZipArchive();
        //create the file and throw the error if unsuccessful
        if ($zip->open($archivePath, ZIPARCHIVE::CREATE) !== true) {
            exit("cannot open <$archivePath>\n");
        }

        $zip->addFile($filePath, basename($filePath));
        $zip->close();

        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename=' . basename($archivePath));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($archivePath));
        readfile($archivePath);
    }
}
