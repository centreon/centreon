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
 * This plugin is a wrapper around the function {@see displaySvg()}
 * which cannot be called directly from the templates.
 *
 * Usage: <pre>
 *     {displaysvg svgPath='SVG-PATH' color='#COLOR' height=200.0 width=300.0}
 * </pre>
 */
class Smarty_Compiler_Displaysvg extends Smarty_Internal_CompileBase
{
    /**
     * @param array<array{ svgPath?: string, color?: string, height?: string, width?: string }> $args
     * @param Smarty_Internal_TemplateCompilerBase $compiler
     *
     * @return string
     */
    public function compile($args, Smarty_Internal_TemplateCompilerBase $compiler): string
    {
        $svgPath = var_export($this->getArg($args, 'svgPath'), true);
        $color = var_export($this->getArg($args, 'color'), true);
        $height = var_export((float) $this->getArg($args, 'height'), true);
        $width = var_export((float) $this->getArg($args, 'width'), true);

        return "<?php displaySvg({$svgPath}, {$color}, {$height}, {$width}); ?>";
    }

    /**
     * @param array<array<string, string>> $args
     * @param string $name
     *
     * @return string
     */
    private function getArg(array $args, string $name): string
    {
        foreach ($args as $arg) {
            if (isset($arg[$name])) {
                return preg_match('#^"(.*)"$#', $arg[$name], $matches)
                    || preg_match('#^\'(.*)\'$#', $arg[$name], $matches)
                    ? $matches[1] : $arg[$name];
            }
        }

        throw new Exception("Missing {$name} argument");
    }
}
