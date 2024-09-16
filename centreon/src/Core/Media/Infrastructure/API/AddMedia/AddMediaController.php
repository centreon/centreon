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
use Core\Common\Infrastructure\Upload\FileCollection;
use Core\Media\Application\UseCase\AddMedia\AddMedia;
use Core\Media\Application\UseCase\AddMedia\AddMediaRequest;
use Core\Media\Infrastructure\API\Exception\MediaException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AddMediaController extends AbstractController
{
    use LoggerTrait;

    public function __invoke(Request $request, AddMedia $useCase, AddMediaPresenter $presenter): Response
    {
        $this->denyAccessUnlessGrantedForApiConfiguration();
        $uploadedFile = '';
        $filesToDeleteAfterProcessing = [];
        try {
            $assertion = new AddMediaValidator($request);
            $assertion->assertFilesSent();
            $assertion->assertDirectory();

            $fileIterator = new FileCollection();

            /** @var UploadedFile|list<UploadedFile> $files */
            $files = $request->files->get('data');
            if (is_array($files)) {
                foreach ($files as $file) {
                    $fileIterator->addFile($file);
                    $filesToDeleteAfterProcessing[] = $file->getPathname();
                }
            } else {
                $fileIterator->addFile($files);
                $filesToDeleteAfterProcessing[] = $files->getPathname();
            }
            $addMediaRequest = new AddMediaRequest($fileIterator->getFiles());
            $addMediaRequest->directory = (string) $request->request->get('directory');

            $useCase($addMediaRequest, $presenter);
        } catch (MediaException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(
                new ErrorResponse(
                    MediaException::errorUploadingFile($uploadedFile)->getMessage()
                )
            );
        } finally {
            foreach ($filesToDeleteAfterProcessing as $fileToDelete) {
                if (is_file($fileToDelete)) {
                    unlink($fileToDelete);
                    $this->debug('Deleting the uploaded file', ['filename' => $fileToDelete]);
                }
            }
        }

        return $presenter->show();
    }
}
