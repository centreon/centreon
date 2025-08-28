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

declare(strict_types=1);

namespace Tests\Core\Media\Application\UseCase\FindImageFolders;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Media\Application\Repository\ReadImageFolderRepositoryInterface;
use Core\Media\Application\UseCase\FindImageFolders\FindImageFolders;
use Core\Media\Domain\Model\ImageFolder\ImageFolder;
use Core\Media\Domain\Model\ImageFolder\ImageFolderDescription;
use Core\Media\Domain\Model\ImageFolder\ImageFolderId;
use Core\Media\Domain\Model\ImageFolder\ImageFolderName;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use Core\Security\AccessGroup\Domain\Model\AccessGroup;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FindImageFoldersTest extends TestCase
{
    private ContactInterface&MockObject $user;

    private RequestParametersInterface&MockObject $requestParameters;

    private ReadAccessGroupRepositoryInterface&MockObject $accessGroupReader;

    private ReadImageFolderRepositoryInterface&MockObject $imageFolderReader;

    protected function setUp(): void
    {
        $this->user = $this->createMock(ContactInterface::class);
        $this->requestParameters = $this->createMock(RequestParametersInterface::class);
        $this->accessGroupReader = $this->createMock(ReadAccessGroupRepositoryInterface::class);
        $this->imageFolderReader = $this->createMock(ReadImageFolderRepositoryInterface::class);
    }

    public function testInvokeAsAdminReturnsAllFolders(): void
    {
        $this->user->method('isAdmin')->willReturn(true);

        $folders = [
            $this->createImageFolder(1, 'Folder 1', 'alias1', 'desc1'),
            $this->createImageFolder(2, 'Folder 2', null, null),
        ];

        $this->imageFolderReader
            ->expects($this->once())
            ->method('findByRequestParameters')
            ->with($this->requestParameters)
            ->willReturn($folders);

        $useCase = new FindImageFolders(
            $this->user,
            $this->requestParameters,
            $this->accessGroupReader,
            $this->imageFolderReader
        );

        $response = $useCase();
        $resultFolders = $response->imageFolders;

        $this->assertCount(2, $resultFolders);
        $this->assertSame('Folder 1', $resultFolders[0]->name()->value);
        $this->assertSame('alias1', $resultFolders[0]->alias()->value);
        $this->assertSame('desc1', $resultFolders[0]->description()->value);
        $this->assertNull($resultFolders[1]->alias()?->value);
    }

    public function testInvokeAsUserWithFullAccessReturnsAllFolders(): void
    {
        $this->user->method('isAdmin')->willReturn(false);

        $accessGroups = [
            new AccessGroup(1, 'group1', 'group1'),
        ];

        $folders = [
            $this->createImageFolder(3, 'User Folder'),
        ];

        $this->accessGroupReader
            ->method('findByContact')
            ->with($this->user)
            ->willReturn($accessGroups);

        $this->imageFolderReader
            ->method('hasAccessToAllImageFolders')
            ->with($accessGroups)
            ->willReturn(true);

        $this->imageFolderReader
            ->method('findByRequestParameters')
            ->with($this->requestParameters)
            ->willReturn($folders);

        $useCase = new FindImageFolders(
            $this->user,
            $this->requestParameters,
            $this->accessGroupReader,
            $this->imageFolderReader
        );

        $response = $useCase();
        $resultFolders = $response->imageFolders;

        $this->assertCount(1, $resultFolders);
        $this->assertSame('User Folder', $resultFolders[0]->name()->value);
    }

    public function testInvokeAsUserWithNoFullAccessReturnsFilteredFolders(): void
    {
        $this->user->method('isAdmin')->willReturn(false);

        $accessGroups = [
            new AccessGroup(1, 'group1', 'group1'),
        ];

        $folders = [
            $this->createImageFolder(3, 'User Folder'),
        ];

        $this->accessGroupReader
            ->method('findByContact')
            ->with($this->user)
            ->willReturn($accessGroups);

        $this->imageFolderReader
            ->method('hasAccessToAllImageFolders')
            ->with($accessGroups)
            ->willReturn(false);

        $this->imageFolderReader
            ->method('findByRequestParametersAndAccessGroups')
            ->with($this->requestParameters, $accessGroups)
            ->willReturn($folders);

        $useCase = new FindImageFolders(
            $this->user,
            $this->requestParameters,
            $this->accessGroupReader,
            $this->imageFolderReader
        );

        $response = $useCase();
        $resultFolders = $response->imageFolders;

        $this->assertCount(1, $resultFolders);
        $this->assertSame('User Folder', $resultFolders[0]->name()->value);
    }

    private function createImageFolder(int $id, string $name, ?string $alias = null, ?string $description = null): ImageFolder
    {
        $folder = new ImageFolder(
            new ImageFolderId($id),
            new ImageFolderName($name)
        );

        $folder->setAlias($alias ? new ImageFolderName($alias) : null);
        $folder->setDescription($description ? new ImageFolderDescription($description) : null);

        return $folder;
    }
}
