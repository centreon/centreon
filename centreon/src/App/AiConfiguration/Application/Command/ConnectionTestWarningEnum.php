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

namespace App\AiConfiguration\Application\Command;

/**
 * What a successful connection test still reveals about the model.
 */
enum ConnectionTestWarningEnum: string
{
    /** Set to reason minimally, the model accepted no setting that turns its reasoning down. */
    case NoReasoningSetting = 'no_reasoning_setting';

    /** The model used its whole answer budget without writing anything, most likely reasoning. */
    case BudgetUsedWithoutAnswer = 'budget_used_without_answer';

    /** The model wrote nothing, within its budget: the AI features reject such an answer. */
    case EmptyAnswer = 'empty_answer';
}
