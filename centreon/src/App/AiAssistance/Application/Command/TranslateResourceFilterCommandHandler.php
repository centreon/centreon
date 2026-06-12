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

namespace App\AiAssistance\Application\Command;

use App\AiAssistance\Domain\ResourceFilterQueryLanguage;
use App\AiConfiguration\Domain\Llm\LlmClientFactoryInterface;
use App\AiConfiguration\Domain\Llm\LlmMessage;
use App\AiConfiguration\Domain\Llm\LlmModelTierEnum;
use App\AiConfiguration\Domain\Llm\LlmRequest;
use App\AiConfiguration\Domain\Llm\LlmRoleEnum;
use App\Shared\Application\Command\AsCommandHandler;

/**
 * Translates a natural-language description into a Resources Status search
 * expression. The model output is validated against the query language; one
 * correction round-trip is attempted on invalid output.
 */
#[AsCommandHandler]
final readonly class TranslateResourceFilterCommandHandler
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You translate a user's natural-language description of monitored resources into a search
        expression for the Centreon Resources Status page.

        Output contract: return ONLY a JSON object of the exact shape {"search": "<expression>"}.
        No markdown fences, no commentary. When the request contains no actionable filtering
        intent, return {"search": ""}.

        Search expression grammar: space-separated tokens. Two kinds of tokens:

        1. Criteria tokens: key:value or key:value1,value2 (comma = OR, no spaces around commas).
           Allowed keys and values:
           - type: host | service | metaservice
           - status: ok | up | warning | down | critical | unreachable | unknown | pending
           - state: unhandled | acknowledged | in_downtime | in_flapping
           - status_type: hard | soft
           - host_group, service_group, host_category, service_category, monitoring_server,
             host_severity, service_severity: values are platform-specific names, copy them
             verbatim from the user request
           - host_severity_level, service_severity_level: integer levels
           - name: service name, parent_name: host name of a service
           Escape spaces inside a value with \s (example: host_group:Linux\sServers).

        2. Free-text tokens: any other word searches resource names and plugin outputs. Targeted
           raw fields are also allowed: h.name:<regex>, h.alias:<regex>, h.address:<regex>,
           s.description:<regex>, parent_alias:<regex>, fqdn:<regex>, information:<regex>.

        Rules:
        - "problems" or "issues" usually means status:warning,critical,down,unreachable,unknown.
        - "unhandled" or "not acknowledged and not in downtime" means state:unhandled.
        - Do not invent group, category or severity names: only use names present in the request.
        - Prefer criteria tokens over free text whenever a key matches the intent.

        Examples:
        - "critical services not yet handled" -> {"search": "type:service status:critical state:unhandled"}
        - "problems on hosts of the Linux Servers group" -> {"search": "type:host status:down,unreachable host_group:Linux\sServers"}
        - "everything about ping on db hosts" -> {"search": "name:ping parent_name:db"}
        PROMPT;
    private const FIX_PROMPT_TEMPLATE = <<<'PROMPT'
        Your previous answer was invalid: %s
        Previous answer: %s
        Return a corrected JSON object {"search": "<expression>"} following the contract. Make the
        smallest change that fixes the problem.
        PROMPT;

    public function __construct(
        private LlmClientFactoryInterface $clientFactory,
    ) {
    }

    public function __invoke(TranslateResourceFilterCommand $command): TranslateResourceFilterResult
    {
        $messages = [
            new LlmMessage(LlmRoleEnum::User, sprintf('User request: %s', $command->prompt)),
        ];

        $content = $this->complete($messages);
        $error = $this->validate($content);

        if ($error !== null) {
            // one correction round-trip, following the previous conversation
            $messages[] = new LlmMessage(LlmRoleEnum::Assistant, $content);
            $messages[] = new LlmMessage(
                LlmRoleEnum::User,
                sprintf(self::FIX_PROMPT_TEMPLATE, $error, $content)
            );

            $content = $this->complete($messages);
            $error = $this->validate($content);
        }

        if ($error !== null) {
            return new TranslateResourceFilterResult(search: '');
        }

        return new TranslateResourceFilterResult(search: $this->extractSearch($content));
    }

    /**
     * @param list<LlmMessage> $messages
     */
    private function complete(array $messages): string
    {
        $client = $this->clientFactory->forActivatedProvider();

        $response = $client->complete(new LlmRequest(
            messages: $messages,
            systemPrompt: self::SYSTEM_PROMPT,
            tier: LlmModelTierEnum::Fast,
            maxTokens: 256,
        ));

        return trim($response->content);
    }

    /**
     * @return string|null an error description, or null when the content is valid
     */
    private function validate(string $content): ?string
    {
        $decoded = json_decode($this->stripCodeFences($content), true);
        if (! is_array($decoded) || ! array_key_exists('search', $decoded) || ! is_string($decoded['search'])) {
            return 'the answer is not a JSON object of the shape {"search": "<expression>"}';
        }

        return ResourceFilterQueryLanguage::validate($decoded['search']);
    }

    private function extractSearch(string $content): string
    {
        /** @var array{search: string} $decoded */
        $decoded = json_decode($this->stripCodeFences($content), true);

        return trim($decoded['search']);
    }

    private function stripCodeFences(string $content): string
    {
        if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', trim($content), $matches) === 1) {
            return $matches[1];
        }

        return $content;
    }
}
