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

declare(strict_types=1);

namespace Core\Application\Common\UseCase;

/**
 * @template TResourceId of int|string|null
 * @template TPayload
 */
final class CreatedResponse implements ResponseStatusInterface
{
    /**
     * @param TResourceId $resourceId
     * @param TPayload $payload
     */
    public function __construct(
        readonly private mixed $resourceId,
        private mixed $payload
    ) {
    }

    /**
     * @return TResourceId
     */
    public function getResourceId(): mixed
    {
        return match (true) {
            null === $this->resourceId,
            is_int($this->resourceId) => $this->resourceId,
            default => (string) $this->resourceId,
        };
    }

    /**
     * @return TPayload
     */
    public function getPayload(): mixed
    {
        return $this->payload;
    }

    /**
     * If you need to change the payload type, use {@see self::withPayload()}.
     *
     * @param TPayload $payload
     */
    public function setPayload(mixed $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * Basically the proper way to avoid the mutation of the internal type.
     *
     * @template TChangedPayload
     *
     * @param TChangedPayload $payload
     *
     * @return self<TResourceId, TChangedPayload>
     */
    public function withPayload(mixed $payload): self
    {
        return new self($this->resourceId, $payload);
    }

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return 'Created';
    }
}
