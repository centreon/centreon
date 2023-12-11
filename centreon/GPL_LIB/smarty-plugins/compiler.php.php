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

/**
 * The {php} tag :
 * - was deprecated in Smarty v3 (in 2011)
 * - was abandoned in Smarty v4 (in 2021)
 *
 * We need to support it, even it is VERY unsafe to use it, because legacy.
 *
 * It was possible to use it Smarty v3 thanks to {@see SmartyBC}.
 */
class Smarty_Compiler_Php extends Smarty_Internal_CompileBase
{
    /**
     * @param array<mixed> $args
     * @param Smarty_Internal_TemplateCompilerBase $compiler
     *
     * @return string
     */
    public function compile($args, Smarty_Internal_TemplateCompilerBase $compiler): string
    {
        return "<?php\n";
    }
}

class Smarty_Compiler_Phpclose extends Smarty_Internal_CompileBase
{
    /**
     * @param array<mixed> $args
     * @param Smarty_Internal_TemplateCompilerBase $compiler
     *
     * @return string
     */
    public function compile(array $args, Smarty_Internal_TemplateCompilerBase $compiler): string
    {
        return "\n?>";
    }
}
