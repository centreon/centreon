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

namespace Centreon\Application\Validation;

use Symfony\Contracts\Translation\TranslatorInterface;

class CentreonValidatorTranslator implements TranslatorInterface
{
    public function __construct(readonly private \CentreonUser $contact)
    {
    }

    /**
     * {@inheritDoc}
     */
    public function trans($id, array $parameters = [], $domain = null, $locale = null): string
    {
        $message = gettext($id);

        foreach ($parameters as $key => $val) {
            $message = str_replace($key, $val, $message);
        }

        return $message;
    }

    /**
     * @codeCoverageIgnore
     * {@inheritDoc}
     */
    public function getLocale(): string
    {
        return $this->contact->get_lang();
    }
}
