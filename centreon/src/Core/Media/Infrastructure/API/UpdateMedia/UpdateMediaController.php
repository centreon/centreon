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

namespace Core\Media\Infrastructure\API\UpdateMedia;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMedia;
use Core\Media\Application\UseCase\UpdateMedia\UpdateMediaRequest;
use Core\Media\Infrastructure\API\AddMedia\AddMediaValidator;
use Core\Media\Infrastructure\API\Exception\MediaException;
use enshrined\svgSanitize\Sanitizer;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class UpdateMediaController extends AbstractController
{
    use LoggerTrait;

    /**
     * @param Sanitizer $svgSanitizer
     */
    public function __construct(private readonly Sanitizer $svgSanitizer)
    {
    }

    #[IsGranted('update_media', null, 'You are not allowed to update media', Response::HTTP_FORBIDDEN)]
    public function __invoke(int $mediaId, Request $request, UpdateMedia $useCase, UpdateMediaPresenter $presenter): Response
    {
        $uploadedFileName = '';
        try {
            $assertion = new AddMediaValidator($request);
            $assertion->assertFilesSent();

            if (is_array($request->files->get('data'))) {
                throw MediaException::moreThanOneFileNotAllowed();
            }

            /** @var UploadedFile $file */
            $file = $request->files->get('data');
            $uploadedFileName = $file->getPathname();

            $updateMediaRequest = new UpdateMediaRequest(
                fileName: $file->getClientOriginalName(),
                data: $this->sanitizeData($file)
            );

            $useCase($mediaId, $updateMediaRequest, $presenter);
        } catch (MediaException $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
        } catch (\Throwable $ex) {
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
            $presenter->presentResponse(
                new ErrorResponse(
                    MediaException::errorUploadingFile($uploadedFileName)->getMessage()
                )
            );
        } finally {
            if (is_file($uploadedFileName)) {
                if (! unlink($uploadedFileName)) {
                    $this->error(
                        'Failed to delete the temporary uploaded file',
                        ['filename' => $uploadedFileName],
                    );
                }
                $this->debug('Deleting the uploaded file', ['filename' => $uploadedFileName]);
            }
        }

        return $presenter->show();
    }

    /**
     * @param UploadedFile $file
     *
     * @throws FileException
     * @return string
     */
    private function sanitizeData(UploadedFile $file): string
    {
        $fileInformation = pathinfo($file->getClientOriginalName());

        if (
            array_key_exists('extension', $fileInformation)
            && $fileInformation['extension'] === 'svg'
        ) {
            $this->svgSanitizer->minify(true);

            return $this->svgSanitizer->sanitize($file->getContent());
        }

        return $file->getContent();
    }
}

