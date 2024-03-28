<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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
 * This plugin is a wrapper around the legacy commonly included file `include/common/pagination.php`
 * which displays a pagination bar in the legacy pages before + after the tables.
 *
 * Usage: <pre>
 *     {pagination}
 * </pre>
 */
class Smarty_Compiler_Pagination extends Smarty_Internal_CompileBase
{
    /**
     * @param array<mixed> $args
     * @param Smarty_Internal_TemplateCompilerBase $compiler
     *
     * @return string
     */
    public function compile($args, Smarty_Internal_TemplateCompilerBase $compiler): string
    {
        return "<?php include('./include/common/pagination.php'); ?>";
    }
}
