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

namespace Core\PublicPage\Infrastructure\API\ServePublicPage;

use Core\Option\Application\Repository\ReadOptionRepositoryInterface;
use Core\PublicPage\Application\Repository\EmbeddingDomainProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ServePublicPageController
{
    private const OPTION_ALLOWED_EMBEDDING_DOMAINS = 'allowed_embedding_domains';

    /**
     * Valid CSP frame-ancestors source: https://example.com, https://*.example.com:8443, etc.
     */
    private const VALID_SOURCE_PATTERN = '/^https?:\/\/(\*\.)?[a-zA-Z0-9]([a-zA-Z0-9.-]*[a-zA-Z0-9])?(:\d+)?$/';

    /**
     * @param iterable<EmbeddingDomainProviderInterface> $embeddingDomainProviders
     */
    public function __construct(
        #[Autowire('%centreon_path%')]
        private readonly string $centreonPath,
        #[AutowireIterator('public_page.embedding_domain.providers')]
        private readonly iterable $embeddingDomainProviders,
    ) {
    }

    #[Route(
        path: '/public/{path}',
        name: 'ServePublicPage',
        methods: ['GET'],
        requirements: ['path' => '.+']
    )]
    public function __invoke(
        string $path,
        ReadOptionRepositoryInterface $optionRepository,
    ): Response {
        $indexHtmlPath = $this->centreonPath . 'www/index.html';
        $indexHtml = file_get_contents($indexHtmlPath);

        if ($indexHtml === false) {
            return new Response('Page not found', Response::HTTP_NOT_FOUND);
        }

        $response = new Response($indexHtml, Response::HTTP_OK, [
            'Content-Type' => 'text/html; charset=UTF-8',
        ]);

        $domainsValue = $this->resolveAllowedDomains($path, $optionRepository);
        $frameAncestors = $this->buildFrameAncestors($domainsValue);

        $response->headers->set('Content-Security-Policy', "frame-ancestors {$frameAncestors}");

        return $response;
    }

    /**
     * Resolve allowed domains: per-resource override first, then global fallback.
     */
    private function resolveAllowedDomains(string $path, ReadOptionRepositoryInterface $optionRepository): ?string
    {
        // Check per-resource providers first
        foreach ($this->embeddingDomainProviders as $provider) {
            if ($provider->supports($path)) {
                $domains = $provider->getAllowedDomains($path);
                if ($domains !== null) {
                    return $domains;
                }
                // Provider supports this path but returned null → fall through to global
                break;
            }
        }

        // Fall back to global setting
        $option = $optionRepository->findByName(self::OPTION_ALLOWED_EMBEDDING_DOMAINS);

        return $option?->getValue();
    }

    private function buildFrameAncestors(?string $domainsValue): string
    {
        if ($domainsValue === null || trim($domainsValue) === '') {
            return "'self'";
        }

        $rawDomains = array_filter(
            array_map('trim', explode(',', $domainsValue))
        );

        // Sanitize: strip header injection characters and keep only valid CSP sources
        $domains = [];
        foreach ($rawDomains as $domain) {
            $sanitized = str_replace(['"', "\r", "\n", ';'], '', $domain);
            if (preg_match(self::VALID_SOURCE_PATTERN, $sanitized) === 1) {
                $domains[] = $sanitized;
            }
        }

        if ($domains === []) {
            return "'self'";
        }

        return "'self' " . implode(' ', $domains);
    }
}
