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

namespace Core\Engine\Infrastructure\Repository;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Common\Domain\Exception\RepositoryException;
use Core\Engine\Application\Repository\EngineRepositoryInterface;
use Core\Engine\Domain\Model\EngineKey;
use Core\Engine\Domain\Model\EngineSecrets;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

final readonly class FsEngineRepository implements EngineRepositoryInterface
{
    private Filesystem $filesystem;

    public function __construct(private string $engineContextPath)
    {
        $this->filesystem = new Filesystem();
    }
    /**
     * Get engine secrets.
     *
     * @return EngineSecrets
     *
     * @throws RepositoryException|AssertionException
     */
    public function getEngineSecrets(): EngineSecrets
    {
        try {
            $engineContextContent = $this->filesystem->readFile($this->engineContextPath);
            $engineContext = json_decode($engineContextContent, true, flags: JSON_THROW_ON_ERROR);

            return new EngineSecrets(
                new EngineKey($engineContext['app_secret']),
                new EngineKey($engineContext['salt'])
            );
        } catch (IOException $ex) {
            throw new RepositoryException(
                'Unable to get content of engine-context file. check that file exists',
                ['path' => $this->engineContextPath, 'exception' => $ex]
            );
        } catch (\JsonException $ex) {
            throw new RepositoryException(
                'engine-context file exists but JSON content is not well formated',
                ['path' => $this->engineContextPath, 'exception' => $ex]
            );
        }
    }
}

