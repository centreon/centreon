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

namespace App\Shared\Infrastructure\ApiPlatform;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The payload of the request being handled, decoded once however many constraints ask for it.
 */
final class CurrentRequestPayload
{
    private ?Request $request = null;

    private ?RequestPayload $payload = null;

    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    /**
     * Null when no request is being handled.
     */
    public function get(): ?RequestPayload
    {
        $request = $this->requestStack->getCurrentRequest();
        if (! $request instanceof Request) {
            return null;
        }

        if (! $this->payload instanceof RequestPayload || $this->request !== $request) {
            $this->request = $request;
            $this->payload = RequestPayload::fromRequest($request);
        }

        return $this->payload;
    }
}
