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

namespace Core\PublicPage\Application\Repository;

/**
 * Provides per-resource allowed embedding domains for public pages.
 *
 * Modules can implement this interface to override the global
 * allowed_embedding_domains setting for specific public paths
 * (e.g., per-playlist embedding domains).
 */
interface EmbeddingDomainProviderInterface
{
    /**
     * Whether this provider can resolve embedding domains for the given path.
     */
    public function supports(string $path): bool;

    /**
     * Return the allowed embedding domains for the given path,
     * as a comma-separated string (same format as the global option).
     *
     * Return null to fall back to the global setting.
     */
    public function getAllowedDomains(string $path): ?string;
}
