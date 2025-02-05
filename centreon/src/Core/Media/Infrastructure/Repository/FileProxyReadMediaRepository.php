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
        private readonly DbReadMediaRepository $dbReadMediaRepository,
        private string $absoluteMediaPath,
    ) {
         $this->absoluteMediaPath = realpath($absoluteMediaPath)
            ?: throw new \Exception(sprintf('Path invalid \'%s\'', $absoluteMediaPath));
    }

    /**
     * @inheritDoc
     */
    public function findById(int $mediaId): ?Media
    {
        return $this->dbReadMediaRepository->findById($mediaId);
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
    public function findAll(): \Traversable&\Countable
    {
        return new class ($this->absoluteMediaPath, $this->dbReadMediaRepository->findAll())
            implements \IteratorAggregate, \Countable {
            /**
             * @param string $absoluteMediaPath
             * @param \Traversable<int, Media>&\Countable $medias
             */
            public function __construct(
                readonly private string $absoluteMediaPath,
                readonly private \Traversable&\Countable $medias
            ) {
            }

            public function getIterator(): \Traversable
            {
                foreach ($this->medias as $media) {
                    $absoluteMediaPath = $this->absoluteMediaPath . DIRECTORY_SEPARATOR . $media->getRelativePath();
                    if (file_exists($absoluteMediaPath)) {
                        yield new Media(
                            $media->getId(),
                            $media->getFilename(),
                            $media->getDirectory(),
                            $media->getComment(),
                            file_get_contents($absoluteMediaPath)
                                ?: throw new \Exception(
                                'Cannot get content of file ' . $media->getRelativePath()
                            )
                        );
                    }
                    else {
                        yield $media;
                    }
                }
            }

            public function count(): int
            {
                return count($this->medias);
            }
        };
    }

    /**
     * @inheritDoc
     */
    public function findByRequestParameters(RequestParametersInterface $requestParameters): \Traversable
    {
        return $this->createTraversable($this->dbReadMediaRepository->findByRequestParameters($requestParameters));
    }

    /**
     * @param \Traversable<int, Media> $medias
     *
     * @return \Traversable<int, Media>
     */
    private function createTraversable(\Traversable $medias): \Traversable
    {
        return new class ($this->absoluteMediaPath, $medias) implements \IteratorAggregate
        {
            /**
             * @param string $absoluteMediaPath
             * @param \Traversable<int, Media> $medias
             */
            public function __construct(
                readonly private string $absoluteMediaPath,
                readonly private \Traversable $medias
            ) {
            }

            public function getIterator(): \Traversable
            {
                foreach ($this->medias as $media) {
                    $absoluteMediaPath = $this->absoluteMediaPath . DIRECTORY_SEPARATOR . $media->getRelativePath();
                    if (file_exists($absoluteMediaPath)) {
                        yield new Media(
                            $media->getId(),
                            $media->getFilename(),
                            $media->getDirectory(),
                            $media->getComment(),
                            file_get_contents($absoluteMediaPath) ?: null
                        );
                    } else {
                        yield $media;
                    }
                }
            }
        };
    }
}
