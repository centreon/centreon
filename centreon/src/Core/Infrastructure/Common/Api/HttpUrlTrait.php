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

namespace Core\Infrastructure\Common\Api;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Service\Attribute\Required;

trait HttpUrlTrait
{
    private ?Request $request = null;

    #[Required]
    public function setHttpServerBag(RequestStack $requestStack): void
    {
        $this->request = $requestStack->getCurrentRequest();
    }

    /**
     * @param bool $withScheme
     *
     * @return string
     */
    public function getHost(bool $withScheme = false): string
    {
        if ($this->request === null) {
            throw new \RuntimeException(
                'Unable to resolve HTTP host: no current request available in the request stack'
            );
        }

        return $withScheme
            ? $this->request->getSchemeAndHttpHost()
            : $this->request->getHttpHost();
    }

    /**
     * Get base URL (example: https://127.0.0.1/centreon).
     *
     * @return string
     */
    protected function getBaseUrl(): string
    {
        $baseUri = trim($this->getBaseUri(), '/');

        return rtrim($this->getHost(true) . '/' . $baseUri, '/');
    }

    /**
     * Get base URI (example: /centreon).
     *
     * @return string
     */
    protected function getBaseUri(): string
    {
        $baseUri = '';

        $routeSuffixPatterns = [
            '(api|widgets|modules|include)\/.+',
            'main(\.get)?\.php',
            '(?<!administration\/)authentication\/.+',
        ];

        if ($this->request === null) {
            throw new \RuntimeException(
                'Unable to resolve HTTP base URI: no current request available in the request stack'
            );
        }

        $requestUri = $this->request->getRequestUri();
        if (
            $requestUri !== ''
            && preg_match('/^(.+?)\/?(' . implode('|', $routeSuffixPatterns) . ')/', $requestUri, $matches)
        ) {
            $baseUri = $matches[1];
        }

        return rtrim($baseUri, '/');
    }
}
