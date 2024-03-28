<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

use Core\Common\Infrastructure\Upload\FileCollection;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

it('should iterate on each files of ZIP archive and others', function(): void {
    $fileManager = new FileCollection();
    $fileManager->addFile(new UploadedFile(__DIR__ . DIRECTORY_SEPARATOR . 'archive.zip', 'archive.zip'));
    $fileManager->addFile(new UploadedFile(__DIR__ . DIRECTORY_SEPARATOR . 'logo.jpg', 'logo.jpg'));
    $fileManager->addFile(new UploadedFile(__DIR__ . DIRECTORY_SEPARATOR . 'logo.svg', 'logo.svg'));

    foreach($fileManager->getFiles() as $filename => $contentFile) {
        echo null; // To ensure that we can iterate several times
    }
    $files = [];
    foreach($fileManager->getFiles() as $filename => $contentFile) {
        /** @var list<array{filename: string, md5: string}> $files */
        $files[] = [
            'filename' => $filename,
            'md5' => md5($contentFile)
        ];
    }
    expect($files)->toHaveCount(4)
        ->and($files[0]['filename'])->toEqual('logo.jpg')
        ->and($files[0]['md5'])->toEqual('f7d5fc06a33946703054046c7174bbf4')
        ->and($files[1]['filename'])->toEqual('logo.svg')
        ->and($files[1]['md5'])->toEqual('50dfb12940f9b30ad4d961ad92b3569b')
        ->and($files[2]['filename'])->toEqual('logo_in_archive.jpg')
        ->and($files[2]['md5'])->toEqual('f7d5fc06a33946703054046c7174bbf4')
        ->and($files[3]['filename'])->toEqual('logo_in_archive.svg')
        ->and($files[3]['md5'])->toEqual('50dfb12940f9b30ad4d961ad92b3569b');
});
