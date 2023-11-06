<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types = 1);

namespace Tests\Core\Common\Infrastructure\Upload;

use Core\Common\Infrastructure\Upload\ZipFileIterator;
use Symfony\Component\HttpFoundation\File\File;

it('should iterate on each files of ZIP archive', function(): void {
    $fileIterator = new ZipFileIterator(new File(__DIR__ . DIRECTORY_SEPARATOR . 'archive.zip'));
    foreach ($fileIterator as $filename => $contentFile) {
        echo null; // To ensure that we can iterate several times
    }
    $files = [];
    foreach($fileIterator as $filename => $contentFile) {
        /** @var File $file */
        $files[] = [
            'filename' => $filename,
            'md5' => md5($contentFile)
        ];
    }
    expect($fileIterator)->toHaveCount(2)
        ->and($files)->toHaveCount(2)
        ->and($files[0]['filename'])->toEqual('logo_in_archive.jpg')
        ->and($files[0]['md5'])->toEqual('f7d5fc06a33946703054046c7174bbf4')
        ->and($files[1]['filename'])->toEqual('logo_in_archive.svg')
        ->and($files[1]['md5'])->toEqual('50dfb12940f9b30ad4d961ad92b3569b');
});
