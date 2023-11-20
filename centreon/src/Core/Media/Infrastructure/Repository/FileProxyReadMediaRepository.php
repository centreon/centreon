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

namespace Core\Media\Infrastructure\Repository;

use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Media\Application\Repository\ReadMediaRepositoryInterface;
use Core\Media\Domain\Model\Media;

class FileProxyReadMediaRepository implements ReadMediaRepositoryInterface
{
    /**
     * @param DbReadMediaRepository $dbReadMediaRepository
     * @param string $absoluteMediaPath
     *
     * @throws \Exception
     */
    public function __construct(
        readonly private DbReadMediaRepository $dbReadMediaRepository,
        private string $absoluteMediaPath,
    ) {
         $this->absoluteMediaPath = realpath($absoluteMediaPath)
            ?: throw new \Exception(sprintf('Path invalid \'%s\'', $absoluteMediaPath));
    }

    /**
     * @inheritDoc
     */
    public function existsByPath(string $path): bool
    {
        return $this->dbReadMediaRepository->existsByPath($path);
    }

    /**
     * @inheritDoc
     */
    public function findAll(): \Iterator&\Countable
    {
        return new class ($this->absoluteMediaPath, $this->dbReadMediaRepository->findAll())
            implements \Iterator, \Countable
        {
            /**
             * @param string $absoluteMediaPath
             * @param \Iterator<int, Media>&\Countable $medias
             */
            public function __construct(
                readonly private string $absoluteMediaPath,
                readonly private \Iterator&\Countable $medias
            ) {
            }

            public function current(): Media
            {
                /** @var Media $media */
                $media = $this->medias->current();
                $absoluteMediaPath = $this->absoluteMediaPath . DIRECTORY_SEPARATOR . $media->getRelativePath();
                if (file_exists($absoluteMediaPath)) {
                    return new Media(
                        $media->getId(),
                        $media->getFilename(),
                        $media->getDirectory(),
                        $media->getComment(),
                        file_get_contents($absoluteMediaPath)
                            ?: throw new \Exception('Impossible to get content of file ' . $media->getRelativePath())
                    );
                }

                return $media;
            }

            public function next(): void
            {
                $this->medias->next();
            }

            public function key(): int
            {
                return $this->medias->key();
            }

            public function valid(): bool
            {
                return $this->medias->valid();
            }

            public function rewind(): void
            {
                $this->medias->rewind();
            }

            public function count(): int
            {
                return $this->medias->count();
            }
        };
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): \Iterator&\Countable
    {
        return $this->createMediaIterator($this->dbReadMediaRepository->findByRequestParameters($requestParameters));
    }

    private function createMediaIterator(\Iterator&\Countable $iterator): \Iterator&\Countable
    {
        return new class ($this->absoluteMediaPath, $iterator)
            implements \Iterator, \Countable
        {
            /**
             * @param string $absoluteMediaPath
             * @param \Iterator<int, Media>&\Countable $medias |\Countable
             */
            public function __construct(
                readonly private string $absoluteMediaPath,
                readonly private \Iterator&\Countable $medias
            ) {
            }

            public function current(): Media
            {
                $media = $this->medias->current();
                $absoluteMediaPath = $this->absoluteMediaPath . DIRECTORY_SEPARATOR . $media->getRelativePath();
                if (file_exists($absoluteMediaPath)) {
                    return new Media(
                        $media->getId(),
                        $media->getFilename(),
                        $media->getDirectory(),
                        $media->getComment(),
                        file_get_contents($absoluteMediaPath)
                            ?: throw new \Exception('Impossible to get content of file ' . $media->getRelativePath())
                    );
                }

                return $media;
            }

            public function next(): void
            {
                $this->medias->next();
            }

            public function key(): int
            {
                return $this->medias->key();
            }

            public function valid(): bool
            {
                return $this->medias->valid();
            }

            public function rewind(): void
            {
                $this->medias->rewind();
            }

            public function count(): int
            {
                return count($this->medias);
            }
        };
    }
}
