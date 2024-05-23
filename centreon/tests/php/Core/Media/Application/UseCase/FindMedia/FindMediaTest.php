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

declare(strict_types=1);

namespace Tests\Core\Media\Domain;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Host\Application\Exception\HostException;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Application\UseCase\FindMedias\FindMedias;
use Core\Media\Application\UseCase\FindMedias\FindMediasPresenterInterface;
use Core\Media\Application\UseCase\FindMedias\FindMediasResponse;
use Core\Media\Application\UseCase\FindMedias\MediaDto;
use Core\Media\Domain\Model\Media;

beforeEach(function (): void {
    $this->readMediaRepository = $this->createMock(ReadMediaRepositoryInterface::class);
    $this->requestParametersInterface = $this->createMock(RequestParametersInterface::class);
    $this->user = $this->createMock(ContactInterface::class);
    $this->presenter = $this->createMock(FindMediasPresenterInterface::class);
});

it('should present a FindMediaResponse when no error occurred', function () {
    $this->useCase = new FindMedias($this->requestParametersInterface, $this->readMediaRepository, $this->user);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW, true],
                [Contact::ROLE_CONFIGURATION_MEDIA_R, true],
                [Contact::ROLE_CONFIGURATION_MEDIA_RW, true],
            ]
        );

    $media = new Media(1, 'media2.png', 'img', null, 'fake data');
    $mediaIterator = new \ArrayIterator([$media]);

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willReturn($mediaIterator);

    $this->presenter
        ->expects($this->once())
        ->method('presentResponse')
        ->with($this->callback(function (FindMediasResponse $response) use ($media) {
            $dto = new MediaDto();
            $dto->id = $media->getId();
            $dto->filename = $media->getFilename();
            $dto->directory = $media->getDirectory();
            $dto->md5 = $media->hash();

            return count($response->medias) === 1 &&
                $response->medias[0]->id === $dto->id &&
                $response->medias[0]->filename === $dto->filename &&
                $response->medias[0]->directory === $dto->directory &&
                $response->medias[0]->md5 === $dto->md5;
        }));

    $this->useCase->__invoke($this->presenter);
});

it('should present an ErrorResponse when an exception occurs', function () {
    $this->useCase = new FindMedias($this->requestParametersInterface, $this->readMediaRepository, $this->user);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW, true],
                [Contact::ROLE_CONFIGURATION_MEDIA_R, true],
                [Contact::ROLE_CONFIGURATION_MEDIA_RW, true],
            ]
        );

    $this->readMediaRepository
        ->expects($this->once())
        ->method('findByRequestParameters')
        ->willThrowException(new \Exception());

    $this->presenter
        ->expects($this->once())
        ->method('presentResponse')
        ->with($this->callback(function (ErrorResponse $response) {
            return $response->getMessage() === MediaException::errorWhileSearchingForMedias()->getMessage();
        }));

    $this->useCase->__invoke($this->presenter);
});

it('should present  Forbidden response when an exception occurs', function () {
    $this->useCase = new FindMedias($this->requestParametersInterface, $this->readMediaRepository, $this->user);

    $this->user
        ->expects($this->any())
        ->method('hasTopologyRole')
        ->willReturnMap(
            [
                [Contact::ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW, false],
                [Contact::ROLE_CONFIGURATION_MEDIA_R, false],
                [Contact::ROLE_CONFIGURATION_MEDIA_RW, false],
            ]
        );

    $this->presenter
        ->expects($this->once())
        ->method('presentResponse')
        ->with($this->callback(function (ForbiddenResponse $response) {
            return $response->getMessage() === MediaException::listingNotAllowed()->getMessage();
        }));

    $this->useCase->__invoke($this->presenter);
});