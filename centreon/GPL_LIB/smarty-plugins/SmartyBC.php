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
 * This class is a pure copy/paste from the Smarty v3 codebase.
 *
 * It was deprecated in v3, but removed in v4, then we needed to reintroduce it to avoid
 * breaking the legacy everywhere.
 *
 * This class was created by smarty in september 2011 : we need to get rid of it asap !
 *
 * "BC" stands for "Backward Compatibility".
 *
 * @see Smarty_Compiler_Php
 */
class SmartyBC extends Smarty
{
    /** @var array<callable-string>  */
    private const SMARTY_V3_DEPRECATED_PHP_MODIFIERS = [
        'count',
        'sizeof',
        'in_array',
        'is_array',
        'time',
        'urlencode',
        'rawurlencode',
        'json_encode',
        'strtotime',
        'number_format',
    ];
    /**
     * Forbidden tags in Smarty templates.
     */
    private const FORBIDDEN_TAGS = ['extends'];

    /**
     * Smarty 2 BC.
     *
     * @var string
     */
    public $_version = self::SMARTY_VERSION;

    /**
     * This is an array of directories where trusted php scripts reside.
     *
     * @var array
     */
    public $trusted_dir = [];

    /**
     * SmartyBC constructor
     *
     * @throws SmartyException
     */
    public function __construct()
    {
        parent::__construct();

        // Check if forbidden tags like 'extends' are used in templates, if yes a SmartyException is thrown.
        $this->registerFilter('pre', [$this, 'checkForbiddenTags']);

        // We need to explicitly define these plugins to avoid breaking a future smarty upgrade.
        foreach (self::SMARTY_V3_DEPRECATED_PHP_MODIFIERS as $phpFunction) {
            $this->registerPlugin(self::PLUGIN_MODIFIER, $phpFunction, $phpFunction);
        }
    }

    /**
     * Factory
     *
     * @param string|null $pathTemplate
     * @param string|null $subDirTemplate
     *
     * @return SmartyBC
     * @throws SmartyException
     */
    public static function createSmartyTemplate(?string $pathTemplate = null, ?string $subDirTemplate = null): SmartyBC
    {
        try {
            $template = new \SmartyBC();

            $template->setTemplateDir($pathTemplate . ($subDirTemplate ?? ''));
            $template->setCompileDir(__DIR__ . '/../SmartyCache/compile');
            $template->setConfigDir(__DIR__ . '/../SmartyCache/config');
            $template->setCacheDir(__DIR__ . '/../SmartyCache/cache');
            $template->addPluginsDir(__DIR__ . '/../smarty-plugins');
            $template->loadPlugin('smarty_function_eval');
            $template->setForceCompile(true);
            $template->setAutoLiteral(false);
            $template->allow_ambiguous_resources = true;

            return $template;
        } catch (SmartyException $e) {
            CentreonLog::create()->error(
                CentreonLog::TYPE_BUSINESS_LOG,
                "Smarty error while initializing smarty template : {$e->getMessage()}",
                ['path_template' => $pathTemplate, 'sub_directory_template' => $subDirTemplate],
                $e
            );
            throw new SmartyException("Smarty error while initializing smarty template : {$e->getMessage()}");
        }
    }

    /**
     * wrapper for assign_by_ref.
     *
     * @param string $tpl_var the template variable name
     * @param mixed &$value the referenced value to assign
     */
    public function assign_by_ref($tpl_var, &$value): void
    {
        $this->assignByRef($tpl_var, $value);
    }

    /**
     * wrapper for append_by_ref.
     *
     * @param string $tpl_var the template variable name
     * @param mixed &$value the referenced value to append
     * @param bool $merge flag if array elements shall be merged
     */
    public function append_by_ref($tpl_var, &$value, $merge = false): void
    {
        $this->appendByRef($tpl_var, $value, $merge);
    }

    /**
     * clear the given assigned template variable.
     *
     * @param string $tpl_var the template variable to clear
     */
    public function clear_assign($tpl_var): void
    {
        $this->clearAssign($tpl_var);
    }

    /**
     * Registers custom function to be used in templates.
     *
     * @param string $function the name of the template function
     * @param string $function_impl the name of the PHP function to register
     * @param bool $cacheable
     * @param mixed $cache_attrs
     *
     * @throws SmartyException
     */
    public function register_function($function, $function_impl, $cacheable = true, $cache_attrs = null): void
    {
        $this->registerPlugin('function', $function, $function_impl, $cacheable, $cache_attrs);
    }

    /**
     * Unregister custom function.
     *
     * @param string $function name of template function
     */
    public function unregister_function($function): void
    {
        $this->unregisterPlugin('function', $function);
    }

    /**
     * Registers object to be used in templates.
     *
     * @param string $object name of template object
     * @param object $object_impl the referenced PHP object to register
     * @param array $allowed list of allowed methods (empty = all)
     * @param bool $smarty_args smarty argument format, else traditional
     * @param array $block_methods list of methods that are block format
     *
     * @throws SmartyException
     *
     * @internal param array $block_functs list of methods that are block format
     */
    public function register_object(
        $object,
        $object_impl,
        $allowed = [],
        $smarty_args = true,
        $block_methods = []
    ): void {
        $allowed = (array) $allowed;
        $smarty_args = (bool) $smarty_args;
        $this->registerObject($object, $object_impl, $allowed, $smarty_args, $block_methods);
    }

    /**
     * Unregister object.
     *
     * @param string $object name of template object
     */
    public function unregister_object($object): void
    {
        $this->unregisterObject($object);
    }

    /**
     * Registers block function to be used in templates.
     *
     * @param string $block name of template block
     * @param string $block_impl PHP function to register
     * @param bool $cacheable
     * @param mixed $cache_attrs
     *
     * @throws SmartyException
     */
    public function register_block($block, $block_impl, $cacheable = true, $cache_attrs = null): void
    {
        $this->registerPlugin('block', $block, $block_impl, $cacheable, $cache_attrs);
    }

    /**
     * Unregister block function.
     *
     * @param string $block name of template function
     */
    public function unregister_block($block): void
    {
        $this->unregisterPlugin('block', $block);
    }

    /**
     * Registers compiler function.
     *
     * @param string $function name of template function
     * @param string $function_impl name of PHP function to register
     * @param bool $cacheable
     *
     * @throws SmartyException
     */
    public function register_compiler_function($function, $function_impl, $cacheable = true): void
    {
        $this->registerPlugin('compiler', $function, $function_impl, $cacheable);
    }

    /**
     * Unregister compiler function.
     *
     * @param string $function name of template function
     */
    public function unregister_compiler_function($function): void
    {
        $this->unregisterPlugin('compiler', $function);
    }

    /**
     * Registers modifier to be used in templates.
     *
     * @param string $modifier name of template modifier
     * @param string $modifier_impl name of PHP function to register
     *
     * @throws SmartyException
     */
    public function register_modifier($modifier, $modifier_impl): void
    {
        $this->registerPlugin('modifier', $modifier, $modifier_impl);
    }

    /**
     * Unregister modifier.
     *
     * @param string $modifier name of template modifier
     */
    public function unregister_modifier($modifier): void
    {
        $this->unregisterPlugin('modifier', $modifier);
    }

    /**
     * Registers a resource to fetch a template.
     *
     * @param string $type name of resource
     * @param array $functions array of functions to handle resource
     */
    public function register_resource($type, $functions): void
    {
        $this->registerResource($type, $functions);
    }

    /**
     * Unregister a resource.
     *
     * @param string $type name of resource
     */
    public function unregister_resource($type): void
    {
        $this->unregisterResource($type);
    }

    /**
     * Registers a prefilter function to apply
     * to a template before compiling.
     *
     * @param callable $function
     *
     * @throws SmartyException
     */
    public function register_prefilter($function): void
    {
        $this->registerFilter('pre', $function);
    }

    /**
     * Unregister a prefilter function.
     *
     * @param callable $function
     */
    public function unregister_prefilter($function): void
    {
        $this->unregisterFilter('pre', $function);
    }

    /**
     * Registers a postfilter function to apply
     * to a compiled template after compilation.
     *
     * @param callable $function
     *
     * @throws SmartyException
     */
    public function register_postfilter($function): void
    {
        $this->registerFilter('post', $function);
    }

    /**
     * Unregister a postfilter function.
     *
     * @param callable $function
     */
    public function unregister_postfilter($function): void
    {
        $this->unregisterFilter('post', $function);
    }

    /**
     * Registers an output filter function to apply
     * to a template output.
     *
     * @param callable $function
     *
     * @throws SmartyException
     */
    public function register_outputfilter($function): void
    {
        $this->registerFilter('output', $function);
    }

    /**
     * Unregister an outputfilter function.
     *
     * @param callable $function
     */
    public function unregister_outputfilter($function): void
    {
        $this->unregisterFilter('output', $function);
    }

    /**
     * load a filter of specified type and name.
     *
     * @param string $type filter type
     * @param string $name filter name
     *
     * @throws SmartyException
     */
    public function load_filter($type, $name): void
    {
        $this->loadFilter($type, $name);
    }

    /**
     * clear cached content for the given template and cache id.
     *
     * @param string $tpl_file name of template file
     * @param string $cache_id name of cache_id
     * @param string $compile_id name of compile_id
     * @param string $exp_time expiration time
     *
     * @return bool
     */
    public function clear_cache($tpl_file = null, $cache_id = null, $compile_id = null, $exp_time = null)
    {
        return $this->clearCache($tpl_file, $cache_id, $compile_id, $exp_time);
    }

    /**
     * clear the entire contents of cache (all templates).
     *
     * @param string $exp_time expire time
     *
     * @return bool
     */
    public function clear_all_cache($exp_time = null)
    {
        return $this->clearCache(null, null, null, $exp_time);
    }

    /**
     * test to see if valid cache exists for this template.
     *
     * @param string $tpl_file name of template file
     * @param string $cache_id
     * @param string $compile_id
     *
     * @return bool
     * @throws SmartyException
     *
     * @throws \Exception
     */
    public function is_cached($tpl_file, $cache_id = null, $compile_id = null)
    {
        return $this->isCached($tpl_file, $cache_id, $compile_id);
    }

    /**
     * clear all the assigned template variables.
     */
    public function clear_all_assign(): void
    {
        $this->clearAllAssign();
    }

    /**
     * clears compiled version of specified template resource,
     * or all compiled template files if one is not specified.
     * This function is for advanced use only, not normally needed.
     *
     * @param string $tpl_file
     * @param string $compile_id
     * @param string $exp_time
     *
     * @return bool results of {@link smarty_core_rm_auto()}
     */
    public function clear_compiled_tpl($tpl_file = null, $compile_id = null, $exp_time = null)
    {
        return $this->clearCompiledTemplate($tpl_file, $compile_id, $exp_time);
    }

    /**
     * Checks whether requested template exists.
     *
     * @param string $tpl_file
     *
     * @return bool
     * @throws SmartyException
     *
     */
    public function template_exists($tpl_file)
    {
        return $this->templateExists($tpl_file);
    }

    /**
     * Returns an array containing template variables.
     *
     * @param string $name
     *
     * @return array
     */
    public function get_template_vars($name = null)
    {
        return $this->getTemplateVars($name);
    }

    /**
     * Returns an array containing config variables.
     *
     * @param string $name
     *
     * @return array
     */
    public function get_config_vars($name = null)
    {
        return $this->getConfigVars($name);
    }

    /**
     * load configuration values.
     *
     * @param string $file
     * @param string $section
     * @param string $scope
     */
    public function config_load($file, $section = null, $scope = 'global'): void
    {
        $this->ConfigLoad($file, $section, $scope);
    }

    /**
     * return a reference to a registered object.
     *
     * @param string $name
     *
     * @return object
     */
    public function get_registered_object($name)
    {
        return $this->getRegisteredObject($name);
    }

    /**
     * clear configuration values.
     *
     * @param string $var
     */
    public function clear_config($var = null): void
    {
        $this->clearConfig($var);
    }

    /**
     * trigger Smarty error.
     *
     * @param string $error_msg
     * @param int $error_type
     */
    public function trigger_error($error_msg, $error_type = E_USER_WARNING): void
    {
        trigger_error("Smarty error: {$error_msg}", $error_type);
    }

    /**
     * Display a Smarty template.
     * Error handling is done here to avoid breaking the legacy.
     * If an error occurs, a generic error message is displayed using the following template :
     * www/include/common/templates/error.ihtml.
     *
     * @param string|null $template
     * @param string|null $cache_id
     * @param string|null $compile_id
     * @param object|null $parent
     *
     * @return void
     */
    public function display($template = null, $cache_id = null, $compile_id = null, $parent = null): void
    {
        try {
            parent::display($template, $cache_id, $compile_id, $parent);
        } catch (Throwable $e) {
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
            } catch (Throwable $e) {
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
     * Check if forbidden tags are used in templates. If yes, a SmartyException is thrown.
     * Forbidden tags are defined in the $forbiddenTags array.
     *
     * @param string $source
     *
     * @return string
     * @throws SmartyException
     */
    public function checkForbiddenTags(string $source): string
    {
        foreach (self::FORBIDDEN_TAGS as $tag) {
            if (preg_match('/\{' . $tag . '\b.*?}/', $source)) {
                throw new SmartyException("The '{{$tag}}' tag is forbidden in templates.");
            }
        }

        return $source;
    }
}
