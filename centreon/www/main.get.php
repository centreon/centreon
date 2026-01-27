<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

require_once __DIR__ . '/../bootstrap.php';

// Set logging options
if (defined('E_DEPRECATED')) {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
} else {
    ini_set('error_reporting', E_ALL & ~E_NOTICE & ~E_STRICT);
}

// Purge Values
foreach ($_GET as $key => $value) {
    if (! is_array($value)) {
        $_GET[$key] = HtmlAnalyzer::sanitizeAndRemoveTags($value);
    }
}

$inputGet = [
    'p' => filter_input(INPUT_GET, 'p', FILTER_SANITIZE_NUMBER_INT),
    'num' => filter_input(INPUT_GET, 'num', FILTER_SANITIZE_NUMBER_INT),
    'o' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['o'] ?? ''),
    'min' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['min'] ?? ''),
    'type' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['type'] ?? ''),
    'search' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['search'] ?? ''),
    'limit' => HtmlAnalyzer::sanitizeAndRemoveTags($_GET['limit'] ?? ''),
];
$inputPost = [
    'p' => filter_input(INPUT_POST, 'p', FILTER_SANITIZE_NUMBER_INT),
    'num' => filter_input(INPUT_POST, 'num', FILTER_SANITIZE_NUMBER_INT),
    'o' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['o'] ?? ''),
    'min' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['min'] ?? ''),
    'type' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['type'] ?? ''),
    'search' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['search'] ?? ''),
    'limit' => HtmlAnalyzer::sanitizeAndRemoveTags($_POST['limit'] ?? ''),
];

$inputs = [];
foreach ($inputGet as $argumentName => $argumentValue) {
    if (! empty($inputGet[$argumentName]) && trim($inputGet[$argumentName]) !== '') {
        $inputs[$argumentName] = $inputGet[$argumentName];
    } elseif (! empty($inputPost[$argumentName]) && trim($inputPost[$argumentName]) !== '') {
        $inputs[$argumentName] = $inputPost[$argumentName];
    } else {
        $inputs[$argumentName] = null;
    }
}

if (empty($p)) {
    $p = $inputs['p'];
}

$o = $inputs['o'];
$min = $inputs['min'];
$type = $inputs['type'];
$search = $inputs['search'];
$limit = $inputs['limit'];
$num = $inputs['num'];

// Include all func
include_once './include/common/common-Func.php';
include_once './include/core/header/header.php';

$centreon->user->setCurrentPage($p);

// LCA Init Common Var
global $is_admin;
$is_admin = $centreon->user->admin;
/**
 * @param int $page
 *
 * @return array{
 *     topology_id: int,
 *     topology_parent: int|null,
 *     topology_page: int|null,
 *     topology_name: string,
 *     topology_url: string|null,
 *     topology_url_substitute: string|null,
 * }
 */
$loadTopology = function (int $page): mixed {
    global $pearDB;
    $query = <<<'SQL'
        SELECT topology_parent, topology_name, topology_id, topology_url, topology_page, topology_url_substitute
        FROM topology
        WHERE topology_page = :page
        SQL;
    $statement = $pearDB->prepare($query);
    $statement->bindValue(':page', $page, PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetch(PDO::FETCH_ASSOC);
};

$getTopologyUrl = function (mixed $data): ?string {
    if (! is_array($data)) {
        return null;
    }

    return $data['topology_url_substitute'] ?? $data['topology_url'];
};

$redirect = $loadTopology((int) $p);

/**
 *  Is server a remote ?
 */
global $isRemote;
$isRemote = false;

$result = $pearDB->query("SELECT `value` FROM `informations` WHERE `key` = 'isRemote'");
if ($row = $result->fetch()) {
    $isRemote = $row['value'] === 'yes';
}

// Disable the page if not enabled.
if (! is_enabled_feature_flag($redirect['topology_feature_flag'] ?? null)) {
    $redirect = false;
}

// Init URL
$url = '';
$acl_page = $centreon->user->access->page($p, true);
if (
    $redirect !== false
    && ($acl_page == CentreonACL::ACL_ACCESS_READ_WRITE || $acl_page == CentreonACL::ACL_ACCESS_READ_ONLY)
) {
    if ($redirect['topology_page'] < 100) {
        $ret = get_child($redirect['topology_page'], $centreon->user->access->topologyStr);
        if ($ret === false || ! $ret['topology_page']) {
            if (($url = $getTopologyUrl($redirect)) && file_exists($url)) {
                reset_search_page($url);
            } else {
                $url = './include/core/errors/alt_error.php';
            }
        } else {
            $ret2 = get_child($ret['topology_page'], $centreon->user->access->topologyStr);
            if ($ret2 === false || $ret2['topology_url_opt']) {
                if (! $o) {
                    $tab = preg_split("/\=/", $ret2['topology_url_opt']);
                    $o = $tab[1];
                }
                $p = $ret2['topology_page'];
            }
            if (($url = $getTopologyUrl($ret2)) && file_exists($url)) {
                reset_search_page($url);
                if ($ret2['topology_url_opt']) {
                    $tab = preg_split("/\=/", $ret2['topology_url_opt']);
                    $o = $tab[1];
                }
            } elseif ($url = $getTopologyUrl($ret)) {
                if ($ret['is_react'] === '1') {
                    // workaround to update react page without refreshing whole page
                    echo '<script>'
                        . 'window.top.history.pushState("", "", ".' . $url . '");'
                        . 'window.top.history.pushState("", "", ".' . $url . '");'
                        . 'window.top.history.go(-1);'
                        . '</script>';

                    exit();
                }
            } else {
                $url = './include/core/errors/alt_error.php';
            }
        }
    } elseif ($redirect['topology_page'] >= 100 && $redirect['topology_page'] < 1000) {
        $ret = get_child($redirect['topology_page'], $centreon->user->access->topologyStr);

        if ($ret === false || ! $ret['topology_page']) {
            if (($url = $getTopologyUrl($redirect)) && file_exists($url)) {
                reset_search_page($url);
            } else {
                $url = './include/core/errors/alt_error.php';
            }
        } else {
            if ($ret['topology_url_opt']) {
                if (! $o) {
                    $tab = preg_split("/\=/", $ret['topology_url_opt']);
                    $o = $tab[1];
                }
                $p = $ret['topology_page'];
            }
            $url = $getTopologyUrl($ret);
            if ($url && file_exists($url)) {
                reset_search_page($url);
            } else {
                $url = './include/core/errors/alt_error.php';
            }
        }
    } elseif ($redirect['topology_page'] >= 1000) {
        $ret = get_child($redirect['topology_page'], $centreon->user->access->topologyStr);
        $url = $getTopologyUrl($redirect);
        if ($ret === false || ! $ret['topology_page']) {
            if ($url && file_exists($url)) {
                reset_search_page($url);
            } else {
                $url = './include/core/errors/alt_error.php';
            }
        } elseif ($url && file_exists($url) && $ret['topology_page']) {
            reset_search_page($url);
        } else {
            $url = './include/core/errors/alt_error.php';
        }
    }
    if (isset($o) && $acl_page == CentreonACL::ACL_ACCESS_READ_ONLY) {
        if ($o == 'c') {
            $o = 'w';
        } elseif ($o == 'a') {
            $url = './include/core/errors/alt_error.php';
        }
    }
} else {
    $url = './include/core/errors/alt_error.php';
}

// Header HTML
include_once './include/core/header/htmlHeader.php';

?>
<div id="centreonMsg" class="react-centreon-message"></div>

<script type='text/javascript'>
    //saving the user locale
    localStorage.setItem('locale', '<?php echo $centreon->user->get_lang(); ?>');
</script>
<?php
if (! $centreon->user->showDiv('header')) {
    ?>
    <script type="text/javascript">
        new Effect.toggle('header', 'appear', {
            duration: 0, afterFinish: function () {
                setQuickSearchPosition();
            }
        });
    </script> <?php
}
if (! $centreon->user->showDiv('menu_3')) {
    ?>
    <script type="text/javascript">
        new Effect.toggle('menu_3', 'appear', {duration: 0});
    </script> <?php
}
if (! $centreon->user->showDiv('menu_2')) {
    ?>
    <script type="text/javascript">
        new Effect.toggle('menu_2', 'appear', {duration: 0});
    </script> <?php
}
?>
    <section class="main section-expand" style="padding-top: 4px;">
<?php
// Display PathWay
if ($min != 1) {
    include_once './include/core/pathway/pathway.php';
}

if (isset($url) && $url) {
    include_once $url;
}

if (! isset($centreon->historyPage)) {
    $centreon->createHistory();
}

// Keep in memory all informations about pagination, keyword for search...
$inputArguments = ['num' => FILTER_SANITIZE_NUMBER_INT, 'limit' => FILTER_SANITIZE_NUMBER_INT];
$inputGet = filter_input_array(
    INPUT_GET,
    $inputArguments
);
$inputPost = filter_input_array(
    INPUT_POST,
    $inputArguments
);

if (isset($url) && $url) {
    foreach ($inputArguments as $argumentName => $argumentFlag) {
        if ($argumentName === 'limit') {
            if (! empty($inputGet[$argumentName])) {
                $centreon->historyLimit[$url] = $inputGet[$argumentName];
            } elseif (! empty($inputPost[$argumentName])) {
                $centreon->historyLimit[$url] = $inputPost[$argumentName];
            } else {
                $centreon->historyLimit[$url] = 30;
            }
        }
    }
}

// Display Footer
if (! $min) {
    echo "\t\t\t</td>\t\t</tr>\t</table>\n</div>";
}
?>
    </section>
<?php
// Include Footer
include_once './include/core/footer/footerPart.php';
