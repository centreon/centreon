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

namespace Tests\Core\Media\Domain;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Media\Domain\Model\NewMedia;

beforeEach(function (): void {
    $this->createMedia = fn(array $arguments = []): NewMedia => new NewMedia(...[
        'filename' => 'filename',
        'directory' => 'directory',
        'data' => 'data',
        ...$arguments,
    ]);
});

it('should throw an exception when the filename property is empty', function (): void {
    ($this->createMedia)(['filename' => '']);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('NewMedia::filename')->getMessage()
);

it('should throw an exception when the directory property is empty', function (): void {
    ($this->createMedia)(['directory' => '']);
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('NewMedia::directory')->getMessage()
);

it('should replace space characters by \'_\' in the filename property', function (): void {
    $newMedia = ($this->createMedia)(['filename' => ' new filename.jpg ']);
    expect($newMedia->getFilename())->toBe('new_filename.jpg');
});

it('should remove the space characters in the path property', function (): void {
    $newMedia = ($this->createMedia)(['directory' => ' new path ']);
    expect($newMedia->getDirectory())->toBe('newpath');
});

it('should remove the space characters in the comment property', function (): void {
    $newMedia = ($this->createMedia)([]);
    $newMedia->setComment(' comments ');
    expect($newMedia->getComment())->toBe('comments');
});
