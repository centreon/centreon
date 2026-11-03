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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\HostTemplate;

use ApiPlatform\Metadata\ApiProperty;
use App\MonitoringConfiguration\Domain\Aggregate\HostTemplate\HostTemplateName;
use App\Shared\Infrastructure\Validator\Constraints\NotNullWhenProvided;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Do not add an `id` property here: it would flip a missing-template 404 into a 422 via
 * InvalidReferenceExceptionListener.
 */
final readonly class DuplicateHostTemplateInput
{
    public function __construct(
        // Uniqueness against hosts and templates is checked by the command handler (409).
        #[ApiProperty(schema: [
            'type' => 'string',
            'minLength' => HostTemplateName::MIN_LENGTH,
            'maxLength' => HostTemplateName::MAX_LENGTH,
            'description' => 'The name of the copy, its spaces turned into underscores. Left out, the copy is named "<source name>_<n>".',
        ])]
        #[NotNullWhenProvided]
        #[Assert\Sequentially([
            new Assert\NotBlank(allowNull: true, normalizer: 'trim'),
            new Assert\Length(max: HostTemplateName::MAX_LENGTH, normalizer: 'trim'),
        ])]
        public ?string $name = null,
    ) {
    }
}
