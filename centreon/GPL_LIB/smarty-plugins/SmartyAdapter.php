<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

/**
 * Class
 *
 * @class SmartyAdapter
 */
class SmartyAdapter
{

    /**
     * Forbidden tags in Smarty templates.
     */
    private const FORBIDDEN_TAGS = ['extends'];

    /** @var SmartyBC */
    private \SmartyBC $smartyBC;

    /**
     * SmartyAdapter constructor
     *
     * @param string|null $pathTemplate
     * @param string|null $subDirTemplate
     *
     * @throws \SmartyException
     */
    public function __construct(?string $pathTemplate = null, ?string $subDirTemplate = null)
    {
        try {
            $this->smartyBC = new \SmartyBC();
            $this->smartyBC->setTemplateDir($pathTemplate . ($subDirTemplate ?? ''));
            $this->smartyBC->setCompileDir(__DIR__ . '/../SmartyCache/compile');
            $this->smartyBC->setConfigDir(__DIR__ . '/../SmartyCache/config');
            $this->smartyBC->setCacheDir(__DIR__ . '/../SmartyCache/cache');
            $this->smartyBC->addPluginsDir(__DIR__ . '/../smarty-plugins');
            $this->smartyBC->loadPlugin('smarty_function_eval');
            $this->smartyBC->setForceCompile(true);
            $this->smartyBC->setAutoLiteral(false);
            $this->smartyBC->allow_ambiguous_resources = true;
            $this->addFilter();
        } catch (\SmartyException $e) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                "Smarty error while initializing smarty template : {$e->getMessage()}",
                ['path_template' => $pathTemplate, 'sub_directory_template' => $subDirTemplate],
                $e
            );
            throw new \SmartyException("Smarty error while initializing smarty template : {$e->getMessage()}");
        }
    }

    /**
     * Factory
     *
     * @param string|null $pathTemplate
     * @param string|null $subDirTemplate
     *
     * @throws \SmartyException
     * @return SmartyAdapter
     */
    public static function createSmartyTemplate(
        ?string $pathTemplate = null,
        ?string $subDirTemplate = null
    ): SmartyAdapter {
        return new self($pathTemplate, $subDirTemplate);
    }

    /**
     * @return SmartyBC
     */
    public function getNativeSmartyBC(): SmartyBC
    {
        return $this->smartyBC;
    }

    /**
     * displays a Smarty template
     *
     * @param string $template   the resource handle of the template file or template object
     * @param mixed  $cache_id   cache id to be used with this template
     * @param mixed  $compile_id compile id to be used with this template
     * @param object $parent     next higher level of Smarty variables
     */
    public function display($template = null, $cache_id = null, $compile_id = null, $parent = null): void
    {
        // fixme : useless, use a hook to improve display ?
        try {
            $this->smartyBC->display($template, $cache_id, $compile_id, $parent);
        } catch (\Throwable $e) {
            CentreonLog::create()->critical(
                CentreonLog::TYPE_BUSINESS_LOG,
                "An error occurred while displaying the following template {$template} : {$e->getMessage()}",
                ['template_name' => $template, 'error_message' => $e->getMessage()],
                $e
            );
            try {
                $smartyErrorTpl = new Smarty();
                $error = "An unexpected error occurred. Please try again later or contact your administrator.";
                $smartyErrorTpl->assign('error', $error);
                $smartyErrorTpl->display(__DIR__ . '/../../www/include/common/templates/error.ihtml');
            } catch (\Throwable $e) {
                CentreonLog::create()->critical(
                    CentreonLog::TYPE_BUSINESS_LOG,
                    "An error occurred while displaying the error template : {$e->getMessage()}",
                    ['error_message' => $e->getMessage()],
                    $e
                );
            }
        }
    }

    /**
     * @throws \SmartyException
     * @return void
     */
    private function addFilter(): void
    {
        $this->smartyBC->smarty->reregisterFilter('pre', [$this, 'checkForbiddenTags']);
    }

    /**
     * Check if forbidden tags are used in templates. If yes, a SmartyException is thrown.
     * Forbidden tags are defined in the $forbiddenTags array.
     *
     * @param string $source
     *
     * @throws \SmartyException
     * @return string
     */
    private function checkForbiddenTags(string $source): string
    {
        foreach (self::FORBIDDEN_TAGS as $tag) {
            if (preg_match('/\{' . $tag . '\b.*?}/', $source)) {
                throw new \SmartyException("The '{{$tag}}' tag is forbidden in templates.");
            }
        }

        return $source;
    }

}