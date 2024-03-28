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

namespace Core\Media\Infrastructure\API\AddMedia;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Media\Application\UseCase\AddMedia\AddMediaPresenterInterface;
use Core\Media\Application\UseCase\AddMedia\AddMediaResponse;

class AddMediaPresenter extends AbstractPresenter implements AddMediaPresenterInterface
{
    public function presentResponse(AddMediaResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            $this->setResponseStatus($response);
        } else {
            $this->present([
                'result' => array_map(fn(array $media) => [
                    'id' => $media['id'],
                    'filename' => $media['filename'],
                    'directory' => $media['directory'],
                    'md5' => $media['md5'],
                ], $response->mediasRecorded),
                'errors' => array_map(fn(array $errors) => [
                    'filename' => $errors['filename'],
                    'directory' => $errors['directory'],
                    'reason' => $errors['reason'],
                ], $response->errors),
            ]);
        }
    }
}
