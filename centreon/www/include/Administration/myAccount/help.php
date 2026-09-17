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

$help = [];

$help['contact_platform_data_sending'] = dgettext(
    'help',
    "No: You don't consent to share your platform data. "
    . 'Contact Details: You consent to share your platform data including your alias and email. '
    . 'Anonymized: You consent to share your platform data, but your alias and email will be anonymized.'
);

$help['show_deprecated_pages'] = dgettext(
    'help',
    'If checked this option will restore the use of the deprecated pages.'
    . 'This includes display of the deprecated pages and internal redirection between pages'
    . 'If not checked this option will enable the full use of the new Monitoring page Resource Status'
);
