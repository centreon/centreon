<?php
/**
 * CENTREON
 *
 * Source Copyright 2005-2015 CENTREON
 *
 * Unauthorized reproduction, copy and distribution
 * are not allowed.
 *
 * For more information : contact@centreon.com
 *
 */

/**
 * This returns an anonymous class to manage alternate CSS class for table lines TR.
 */
function getLineTemplate(string $evenCssClass, string $oddCssClass): object
{
    return new class($evenCssClass, $oddCssClass)
    {
        private int $counter = 0;

        public function __construct(private string $evenCssClass, private string $oddCssClass)
        {
        }

        public function get(): string
        {
            return ($this->counter++ % 2) ? $this->oddCssClass : $this->evenCssClass;
        }

        public function reset(): string
        {
            $this->counter = 0;
            return '';
        }
    };
}

function versionCentreon($pearDB)
{
    if (is_null($pearDB)) {
        throw new \Exception('No Database connect available');
    }

    $query = 'SELECT `value` FROM `informations` WHERE `key` = "version"';
    $dbResult = $pearDB->query($query);
    if (!$dbResult) {
        throw new \Exception("An error occured");
    }
    $row = $dbResult->fetch();

    return $row['value'];
}

function getWikiConfig($pearDB)
{
    $errorMsg = 'MediaWiki is not installed or configured. Please refer to the ' .
        '<a href="https://docs.centreon.com/docs/administration/knowledge-base/" target="_blank" >' .
        'documentation.</a>';

    if (is_null($pearDB)) {
        throw new \Exception($errorMsg);
    }

    $res = $pearDB->query("SELECT * FROM `options` WHERE options.key LIKE 'kb_wiki_url'");

    if ($res->rowCount() == 0) {
        throw new \Exception($errorMsg);
    }

    $gopt = [];
    $opt = $res->fetchRow();


    if (empty($opt["value"])) {
        throw new \Exception($errorMsg);
    } else {
        $gopt[$opt["key"]] = html_entity_decode($opt["value"], ENT_QUOTES, "UTF-8");
    }

    $pattern = '#^http://|https://#';
    $WikiURL = $gopt['kb_wiki_url'];
    $checkWikiUrl = preg_match($pattern, $WikiURL);

    if (!$checkWikiUrl) {
        $gopt['kb_wiki_url'] = 'http://' . $WikiURL;
    }

    $res->closeCursor();
    return $gopt;
}


function getWikiVersion($apiWikiURL)
{
    if (is_null($apiWikiURL)) {
        return;
    }

    $post = [
        'action' => 'query',
        'meta' => 'siteinfo',
        'format' => 'json',
    ];

    $data = http_build_query($post);

    /* Get contents */
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $apiWikiURL);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-type: application/x-www-form-urlencoded']);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
    $content = curl_exec($curl);
    curl_close($curl);
    $content = json_decode($content);

    $wikiStringVersion = $content->query->general->generator;
    $wikiDataVersion = explode(' ', $wikiStringVersion);
    $wikiVersion = (float)$wikiDataVersion[1];

    return $wikiVersion;
}
