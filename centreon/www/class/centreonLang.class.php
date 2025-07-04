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
 * Class
 *
 * @class CentreonLang
 */
class CentreonLang
{
    /** @var string */
    protected $charset = 'UTF-8';

    /** @var string */
    protected $lang;

    /** @var string */
    protected $path;

    /** @var array */
    protected $charsetList;

    /**
     * CentreonLang constructor
     *
     * @param string $centreon_path
     * @param Centreon $centreon
     */
    public function __construct($centreon_path, $centreon = null)
    {
        if (! is_null($centreon) && isset($centreon->user->charset)) {
            $this->charset = $centreon->user->charset;
        }

        $this->lang = $this->getBrowserDefaultLanguage() . '.' . $this->charset;
        if (! is_null($centreon) && isset($centreon->user->lang)) {
            if ($centreon->user->lang !== 'browser') {
                $this->lang = $centreon->user->lang;
            }
        }
        $this->path = $centreon_path;
        $this->setCharsetList();
    }

    /**
     * @return int|string|null
     */
    private function parseHttpAcceptHeader()
    {
        $langs = [];

        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            // break up string into pieces (languages and q factors)
            preg_match_all(
                '/([a-z]{1,8}(-[a-z]{1,8})?)\s*(;\s*q\s*=\s*(1|0\.[0-9]+))?/i',
                $_SERVER['HTTP_ACCEPT_LANGUAGE'],
                $lang_parse
            );

            if ($lang_parse[1] !== []) {
                // create a list like "en" => 0.8
                $langs = array_combine($lang_parse[1], $lang_parse[4]);

                // set default to 1 for any without q factor
                foreach ($langs as $lang => $val) {
                    if ($val === '') {
                        $langs[$lang] = 1;
                    }
                }

                // sort list based on value
                arsort($langs, SORT_NUMERIC);
            }
        }

        $languageLocales = array_keys($langs);

        $current = array_shift($languageLocales);
        $favoriteLanguage = $current;

        return $favoriteLanguage;
    }

    /**
     * Used to get the language set in the browser
     *
     * @return string
     */
    private function getBrowserDefaultLanguage()
    {
        $currentLocale = '';

        if (version_compare(PHP_VERSION, '5.2.0') >= 0 && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $browserLocale = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            $currentLocale .= Locale::acceptFromHttp($browserLocale);
        } else {
            $currentLocale .= $this->parseHttpAcceptHeader();
        }

        return $this->getFullLocale($currentLocale);
    }

    /**
     * Used to convert the browser language's from a short string to a string
     *
     * @param string $shortLocale
     * @return string $fullLocale
     */
    private function getFullLocale($shortLocale)
    {
        $fullLocale = '';

        $as = ['fr' => 'fr_FR', 'fr_FR' => 'fr_FR', 'en' => 'en_US', 'en_US' => 'en_US'];

        if (isset($as[$shortLocale])) {
            $fullLocale .= $as[$shortLocale];
        } else {
            $fullLocale = 'en_US';
        }

        return $fullLocale;
    }

    /**
     *  Sets list of charsets
     *
     * @return void
     */
    private function setCharsetList(): void
    {
        $this->charsetList = ['ISO-8859-1', 'ISO-8859-2', 'ISO-8859-3', 'ISO-8859-4', 'ISO-8859-5', 'ISO-8859-6', 'ISO-8859-7', 'ISO-8859-8', 'ISO-8859-9', 'UTF-80', 'UTF-83', 'UTF-84', 'UTF-85', 'UTF-86', 'ISO-2022-JP', 'ISO-2022-KR', 'ISO-2022-CN', 'WINDOWS-1251', 'CP866', 'KOI8', 'KOI8-E', 'KOI8-R', 'KOI8-U', 'KOI8-RU', 'ISO-10646-UCS-2', 'ISO-10646-UCS-4', 'UTF-7', 'UTF-8', 'UTF-16', 'UTF-16BE', 'UTF-16LE', 'UTF-32', 'UTF-32BE', 'UTF-32LE', 'EUC-CN', 'EUC-GB', 'EUC-JP', 'EUC-KR', 'EUC-TW', 'GB2312', 'ISO-10646-UCS-2', 'ISO-10646-UCS-4', 'SHIFT_JIS'];
        sort($this->charsetList);
    }

    /**
     *  Binds lang to the current Centreon page
     *
     * @param mixed $domain
     * @param mixed $path
     * @return void
     */
    public function bindLang($domain = 'messages', $path = 'www/locale/'): void
    {
        putenv("LANG={$this->lang}");
        setlocale(LC_ALL, $this->lang);
        bindtextdomain($domain, $this->path . $path);
        bind_textdomain_codeset($domain, $this->charset);
        textdomain('messages');
    }

    /**
     *  Lang setter
     *
     * @param string $newLang
     * @return void
     */
    public function setLang($newLang): void
    {
        $this->lang = $newLang;
    }

    /**
     *  Returns lang that is being used
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     *  Charset Setter
     *
     * @param string $newCharset
     * @return void
     */
    public function setCharset($newCharset): void
    {
        $this->charset = $newCharset;
    }

    /**
     *  Returns charset that is being used
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     *  Returns an array with a list of charsets
     *
     * @return array
     */
    public function getCharsetList()
    {
        return $this->charsetList;
    }
}
