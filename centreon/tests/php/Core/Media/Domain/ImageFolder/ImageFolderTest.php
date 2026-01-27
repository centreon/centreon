<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace Tests\Core\Media\Domain\ImageFolder;

use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\Media\Domain\Model\ImageFolder\ImageFolderDescription;
use Core\Media\Domain\Model\ImageFolder\ImageFolderId;
use Core\Media\Domain\Model\ImageFolder\ImageFolderName;
use PHPUnit\Framework\TestCase;

final class ImageFolderTest extends TestCase
{
    public function testValidImageFolderIsCreatedCorrectly(): void
    {
        $id = new ImageFolderId(1);
        $name = new ImageFolderName('Main Folder');

        $folder = new ImageFolder($id, $name);

        $this->assertSame($id, $folder->id());
        $this->assertSame($name, $folder->name());
        $this->assertNull($folder->alias());
        $this->assertNull($folder->description());
    }

    public function testIdThrowsWhenNull(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Id of "Core\Media\Domain\Model\ImageFolder\ImageFolder" is not defined');

        $folder = new ImageFolder(null, new ImageFolderName('No ID'));
        $folder->id(); // Should throw
    }

    public function testSetAliasAndDescription(): void
    {
        $folder = new ImageFolder(new ImageFolderId(2), new ImageFolderName('Folder'));

        $alias = new ImageFolderName('Alias Name');
        $desc = new ImageFolderDescription('Description here');

        $folder->setAlias($alias)->setDescription($desc);

        $this->assertSame($alias, $folder->alias());
        $this->assertSame($desc, $folder->description());
    }

    public function testSetAliasAndDescriptionToNull(): void
    {
        $folder = new ImageFolder(new ImageFolderId(3), new ImageFolderName('Another Folder'));

        $folder->setAlias(null)->setDescription(null);

        $this->assertNull($folder->alias());
        $this->assertNull($folder->description());
    }
}
