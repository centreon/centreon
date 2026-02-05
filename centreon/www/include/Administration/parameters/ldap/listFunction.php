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

/**
 * Get toolbar action list
 *
 * @param string $domName
 * @return array
 */
function getActionList($domName)
{
    return ['onchange' => 'javascript: '
        . "if (this.form.elements['{$domName}'].selectedIndex == 1 && confirm('"
        . _('Do you confirm the deletion ?') . "')) {"
        . " 	setA(this.form.elements['{$domName}'].value); submit();} "
        . "else if (this.form.elements['{$domName}'].selectedIndex == 2) {"
        . " 	setA(this.form.elements['{$domName}'].value); submit();} "
        . "else if (this.form.elements['{$domName}'].selectedIndex == 3) {"
        . " 	setA(this.form.elements['{$domName}'].value); submit();} "
        . "this.form.elements['{$domName}'].selectedIndex = 0"];
}
