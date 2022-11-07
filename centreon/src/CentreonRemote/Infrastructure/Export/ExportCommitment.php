<?php
<<<<<<< HEAD

=======
>>>>>>> centreon/dev-21.10.x
/*
 * Copyright 2005 - 2019 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace CentreonRemote\Infrastructure\Export;

use CentreonRemote\Infrastructure\Export\ExportParserJson;
use CentreonRemote\Infrastructure\Export\ExportParserInterface;

final class ExportCommitment
{
<<<<<<< HEAD
    /**
     * @var int[]
     */
    private $pollers;
=======

    /**
     * @var int
     */
    private $poller;
>>>>>>> centreon/dev-21.10.x

    /**
     * @var string
     */
    private $path;

    /**
     * @var \CentreonRemote\Infrastructure\Export\ExportParserInterface
     */
    private $parser;

    /**
<<<<<<< HEAD
     * @var array<mixed>
=======
     * @var array
>>>>>>> centreon/dev-21.10.x
     */
    private $exporters;

    /**
<<<<<<< HEAD
     * @var array<mixed>
     */
    private $meta;

    /**
=======
>>>>>>> centreon/dev-21.10.x
     * @var int
     */
    private $filePermission = 0775;

    /**
<<<<<<< HEAD
     * @var int
     */
    private $remote;

    /**
=======
>>>>>>> centreon/dev-21.10.x
     * Construct
     *
     * @param int $remote
     * @param int[] $pollers
<<<<<<< HEAD
     * @param array<mixed> $meta
     * @param \CentreonRemote\Infrastructure\Export\ExportParserInterface $parser
     * @param string $path
     * @param array<int,string> $exporters
=======
     * @param array $meta
     * @param \CentreonRemote\Infrastructure\Export\ExportParserInterface $parser
     * @param string $path
     * @param array $exporters
>>>>>>> centreon/dev-21.10.x
     */
    public function __construct(
        int $remote = null,
        array $pollers = null,
        array $meta = null,
        ExportParserInterface $parser = null,
        string $path = null,
        array $exporters = null
    ) {
        if ($remote && $pollers && !in_array($remote, $pollers)) {
            $pollers[] = $remote;
        }

        $this->remote = $remote;
        $this->pollers = $pollers;
        $this->meta = $meta;
        $this->path = $path;
        $this->exporters = $exporters ?? [];

        if ($this->path === null) {
            $this->path = _CENTREON_CACHEDIR_ . '/config/export/' . $this->remote;
        }

<<<<<<< HEAD
        $this->parser = $parser ?? new ExportParserJson();
=======
        $this->parser = $parser ?? new ExportParserJson;
>>>>>>> centreon/dev-21.10.x
    }

    public function getRemote(): int
    {
        return $this->remote;
    }

<<<<<<< HEAD
    /**
     * @return int[]
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getPollers(): array
    {
        return $this->pollers;
    }

<<<<<<< HEAD
    /**
     * @return array<mixed>|null
     */
    public function getMeta()
=======
    public function getMeta(): ?array
>>>>>>> centreon/dev-21.10.x
    {
        return $this->meta;
    }

    public function getPath(): string
    {
        return $this->path;
    }

<<<<<<< HEAD
    /**
     * @return array<mixed>
     */
=======
>>>>>>> centreon/dev-21.10.x
    public function getExporters(): array
    {
        return $this->exporters;
    }

    public function getFilePermission(): int
    {
        return $this->filePermission;
    }

    public function getParser(): ExportParserInterface
    {
        return $this->parser;
    }
}
