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

namespace Core\Media\Infrastructure\API\AddMedia;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Media\Application\UseCase\AddMedia\AddMedia;
use Core\Media\Application\UseCase\AddMedia\AddMediaRequest;
use Core\Media\Application\UseCase\AddMedia\MediaDto;
use Core\Media\Infrastructure\API\Exception\AddMediaException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AddMediaController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(Request $request, AddMedia $useCase, AddMediaPresenter $presenter): Response
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $uploadedFile = '';
        try {
            $assertion = new AddMediaValidator($request);
            $assertion->assertFileSent();
            $assertion->assertDirectory();

            /** @var UploadedFile $file */
            $file = $request->files->get('data');
            $uploadedFile = $file->getFilename();
            $fileManager = new UploadMediaFileManager($file);
            $addMediaRequest = new AddMediaRequest($this->createDto($fileManager));
            $addMediaRequest->directory = (string) $request->request->get('directory');

            $useCase($addMediaRequest, $presenter);
            unlink($file->getPathname());
        } catch (AddMediaException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(
                new ErrorResponse(
                    AddMediaException::errorUploadingFile($uploadedFile)->getMessage()
                )
            );
        }

        return $presenter->show();
    }

    /**
     * @param UploadMediaFileManager $manager
     *
     * @throws \Exception
     *
     * @return \Generator<MediaDto>
     */
    private function createDto(UploadMediaFileManager $manager): \Generator
    {
        $manager->addMimeTypeFilter('image/png', 'image/gif', 'image/jpeg', 'image/svg+xml');
        foreach ($manager->getFiles() as $fileInfo) {
            [$fileName, $rawData] = $fileInfo;

            yield new MediaDto($fileName, $rawData);
        }
    }
}
