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

namespace Core\Common\Infrastructure\Repository;

trait ApiRepositoryTrait
{
    protected ?string $proxy = null;

    protected ?string $url = null;

    protected string $authenticationToken = '';

    /** @var positive-int */
    protected int $timeout = 60; // Default timeout

    /** @var positive-int */
    protected int $maxItemsByRequest = 100;

    /** @var positive-int */
    protected int $maxQueryStringLength = 2048;

    public function setProxy(string $proxy): void
    {
        $this->proxy = $proxy;
    }

    public function setUrl(string $url): void
    {
        $this->url = rtrim($url, '/');
        if (! str_starts_with($this->url, 'http')) {
            $this->url = 'https://' . $this->url;
        }
    }

    public function setAuthenticationToken(string $token): void
    {
        $this->authenticationToken = $token;
    }

    public function setTimeout(int $timeout): void
    {
        if ($timeout > 1) {
            $this->timeout = $timeout;
        }
    }

    public function setMaxItemsByRequest(int $maxItemsByRequest): void
    {
        if ($maxItemsByRequest > 1) {
            $this->maxItemsByRequest = $maxItemsByRequest;
        }
    }

    public function setMaxQueryStringLength(int $maxQueryStringLength): void
    {
        if ($maxQueryStringLength > 1) {
            $this->maxQueryStringLength = $maxQueryStringLength;
        }
    }
}
