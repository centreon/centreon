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

namespace Core\Media\Application\UseCase\FindMedias;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Media\Application\Exception\MediaException;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;

final class FindMedias
{
    use LoggerTrait;

    public function __construct(
        readonly private RequestParametersInterface $requestParameters,
        readonly private ReadMediaRepositoryInterface $readMediaRepository,
        readonly private ContactInterface $user,
    ) {
    }

    /**
     * @param FindMediasPresenterInterface $presenter
     */
    public function __invoke(FindMediasPresenterInterface $presenter): void
    {
        try {
            $this->info(
                'Find medias',
                ['user' => $this->user->getId(), 'request' => $this->requestParameters->toArray()]
            );
            if (! $this->canAccessToListing()) {
                $this->error(
                    "User doesn't have sufficient rights to list media",
                    ['user_id' => $this->user->getId()]
                );
                $presenter->presentResponse(new ForbiddenResponse(MediaException::listingNotAllowed()));

                return;
            }
            $medias = $this->readMediaRepository->findByRequestParameters($this->requestParameters);
            $presenter->presentResponse($this->createResponse($medias));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse(MediaException::errorWhileSearchingForMedias()));
        }
    }

    private function canAccessToListing(): bool
    {
        return $this->user->hasTopologyRole(Contact::ROLE_ADMINISTRATION_PARAMETERS_IMAGES_RW);
    }

    /**
     * @param \Traversable<int, Media> $medias
     *
     * @return FindMediasResponse
     */
    private function createResponse(\Traversable $medias): FindMediasResponse
    {
        $response = new FindMediasResponse();
        foreach ($medias as $media) {
            $dto = new MediaDto();
            $dto->id = $media->getId();
            $dto->filename = $media->getFilename();
            $dto->directory = $media->getDirectory();
            $dto->md5 = $media->hash();
            $response->medias[] = $dto;
        }

        return $response;
    }
}
