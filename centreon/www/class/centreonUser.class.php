<?php
/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 */

require_once __DIR__ . '/centreonACL.class.php';
require_once __DIR__ . '/centreonLog.class.php';
require_once __DIR__ . '/centreonAuth.class.php';

/**
 * Class
 *
 * @class CentreonUser
 */
class CentreonUser
{
    /** @var int|string|null */
    public $user_id;
    /** @var string|null */
    public $name;
    /** @var string|null */
    public $alias;
    /** @var string|null */
    public $passwd;
    /** @var string|null */
    public $email;
    /** @var string|null */
    public $lang;
    /** @var string */
    public $charset = "UTF-8";
    /** @var int */
    public $version = 3;
    /** @var int|string|null */
    public $admin;
    /** @var */
    public $limit; //FIXME not in this class
    /** @var */
    public $num; //FIXME not in this class
    /** @var mixed|null */
    public $gmt;
    /** @var bool|null */
    public $is_admin = null;
    /** @var */
    public $groupList; //FIXME not in this class
    /** @var */
    public $groupListStr; //FIXME not in this class
    /** @var CentreonACL */
    public $access;
    /** @var CentreonUserLog */
    public $log;
    /** @var string|null */
    protected $token;
    /** @var int|mixed */
    public $default_page;
    /** @var bool */
    private $showDeprecatedPages;
    /** @var int */
    private $currentPage;
    /** @var string|null */
    public $theme;

    /** @var bool */
    protected $restApi;
    /** @var bool */
    protected $restApiRt;

    # User LCA
    # Array with elements ID for loop test
    /** @var array|null */
    public $lcaTopo;

    # String with elements ID separated by commas for DB requests
    /** @var string|null */
    public $lcaTStr;

    /** @var string */
    public $authType;

    /**
     * CentreonUser constructor
     *
     * @param array $user
     *
     * @throws PDOException
     */
    public function __construct($user = [])
    {
        global $pearDB;

        $this->user_id = $user["contact_id"] ?? null;
        $this->name = isset($user["contact_name"]) ?
            html_entity_decode($user["contact_name"], ENT_QUOTES, "UTF-8") : null;
        $this->alias = isset($user["contact_alias"]) ?
            html_entity_decode($user["contact_alias"], ENT_QUOTES, "UTF-8") : null;
        $this->email = isset($user["contact_email"]) ?
            html_entity_decode($user["contact_email"], ENT_QUOTES, "UTF-8") : null;
        $this->lang = $user["contact_lang"] ?? null;
        $this->passwd = $user["contact_passwd"] ?? null;
        $this->token = $user['contact_autologin_key'] ?? null;
        $this->admin = $user["contact_admin"] ?? null;
        $this->default_page = $user["default_page"] ?? CentreonAuth::DEFAULT_PAGE;
        $this->gmt = $user["contact_location"] ?? null;
        $this->showDeprecatedPages = (bool) $user["show_deprecated_pages"];
        $this->theme = $user["contact_theme"] ?? 'light';
        /*
         * Initiate ACL
         */
        $this->access = new CentreonACL($this->user_id, $this->admin);
        $this->lcaTopo = $this->access->topology;
        $this->lcaTStr = $this->access->topologyStr;
        /*
         * Initiate Log Class
         */
        $this->log = new CentreonUserLog($this->user_id, $pearDB);

        /**
         * Init rest api auth
         */
        $this->restApi = isset($user['reach_api']) && $user['reach_api'] == 1;
        $this->restApiRt = isset($user['reach_api_rt']) && $user['reach_api_rt'] == 1;

        /*
         * Init authentication type, could by local, openid, web-sso, saml
         */
        $this->authType = $user['auth_type'] ?? 'unknown';
    }

    /**
     * @param $div_name
     *
     * @return int|mixed
     */
    public function showDiv($div_name = null)
    {
        global $pearDB;

        if (!isset($div_name)) {
            return 0;
        }
        return $_SESSION['_Div_' . $div_name] ?? 1;
    }

    /**
     * @param CentreonDB $pearDB
     *
     * @return array
     * @throws PDOException
     */
    public function getAllTopology($pearDB)
    {
        $DBRESULT = $pearDB->query("SELECT topology_page FROM topology WHERE topology_page IS NOT NULL");
        while ($topo = $DBRESULT->fetch()) {
            if (isset($topo["topology_page"])) {
                $lcaTopo[$topo["topology_page"]] = 1;
            }
        }
        unset($topo);
        $DBRESULT->closeCursor();
        return $lcaTopo;
    }

    /**
     * Check if user is admin or had ACL
     *
     * @param string $sid
     * @param CentreonDB $pearDB
     *
     * @throws PDOException
     */
    public function checkUserStatus($sid, $pearDB): void
    {
        $query1 = "SELECT contact_admin, contact_id FROM session, contact " .
            "WHERE session.session_id = :session_id" .
            " AND contact.contact_id = session.user_id AND contact.contact_register = '1'";
        $statement = $pearDB->prepare($query1);
        $statement->bindValue(':session_id', $sid);
        $statement->execute();
        $admin = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        $query2 = "SELECT count(*) FROM `acl_group_contacts_relations` " .
            "WHERE contact_contact_id = :contact_id";
        $statement = $pearDB->prepare($query2);
        $statement->bindValue(':contact_id', (int)$admin["contact_id"], PDO::PARAM_INT);
        $statement->execute();
        $admin2 = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if ($admin["contact_admin"]) {
            unset($admin);
            $this->is_admin = 1;// FIXME property overwritten
        } elseif (!$admin2["count(*)"]) {
            unset($admin2);
            $this->is_admin = 1;// FIXME property overwritten
        }
        $this->is_admin = 0;
    }

    // Get

    /**
     * @return int|mixed|string|null
     */
    public function get_id()
    {
        return $this->user_id;
    }

    /**
     * @return string|null
     */
    public function get_name()
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function get_email()
    {
        return $this->email;
    }

    /**
     * @return string|null
     */
    public function get_alias()
    {
        return $this->alias;
    }

    /**
     * @return int
     */
    public function get_version()
    {
        return $this->version;
    }

    /**
     * @return string
     */
    public function get_lang(): string
    {
        $lang = $this->lang;

        // Get locale from browser
        if ($lang === 'browser') {
            if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $lang = Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            }

            // check that the variable value end with .UTF-8 or add it
            $lang = (str_contains($lang, '.UTF-8')) ?: $lang . '.UTF-8';
        }

        return $lang;
    }

    /**
     * @return mixed|string|null
     */
    public function get_passwd()
    {
        return $this->passwd;
    }

    /**
     * @return int|string|null
     */
    public function get_admin()
    {
        return $this->admin;
    }

    /**
     * @return bool|null
     */
    public function is_admin()
    {
        return $this->is_admin;
    }

    /**
     * @return bool
     */
    public function doesShowDeprecatedPages()
    {
        return $this->showDeprecatedPages;
    }

    /**
     * @param bool $showDeprecatedPages
     *
     * @return void
     */
    public function setShowDeprecatedPages(bool $showDeprecatedPages): void
    {
        $this->showDeprecatedPages = $showDeprecatedPages;
    }

    // Set

    /**
     * @param $id
     *
     * @return void
     */
    public function set_id($id): void
    {
        $this->user_id = $id;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function set_name($name): void
    {
        $this->name = $name;
    }

    /**
     * @param string $email
     *
     * @return void
     */
    public function set_email($email): void
    {
        $this->email = $email;
    }

    /**
     * @param string $lang
     *
     * @return void
     */
    public function set_lang($lang): void
    {
        $this->lang = $lang;
    }

    /**
     * @param string $alias
     *
     * @return void
     */
    public function set_alias($alias): void
    {
        $this->alias = $alias;
    }

    /**
     * @param string $version
     *
     * @return void
     */
    public function set_version($version): void
    {
        $this->version = $version;
    }

    /**
     * @return mixed|null
     */
    public function getMyGMT()
    {
        return $this->gmt;
    }

    /**
     * @param CentreonDB $db
     *
     * @return array|mixed
     * @throws PDOException
     */
    public function getUserList($db)
    {
        static $userList;

        if (!isset($userList)) {
            $userList = [];
            $res = $db->query(
                "SELECT contact_id, contact_name
                FROM contact
                WHERE contact_register = '1'
                AND contact_activate = '1'
                ORDER BY contact_name"
            );
            while ($row = $res->fetchRow()) {
                $userList[$row['contact_id']] = $row['contact_name'];
            }
        }
        return $userList;
    }

    /**
     * Get Contact Name
     *
     * @param CentreonDB $db
     * @param int $userId
     *
     * @return string
     * @throws PDOException
     */
    public function getContactName($db, $userId)
    {
        static $userNames;

        if (!isset($userNames)) {
            $userNames = [];
            $res = $db->query("SELECT contact_name, contact_id FROM contact");
            while ($row = $res->fetch()) {
                $userNames[$row['contact_id']] = $row['contact_name'];
            }
        }
        return $userNames[$userId] ?? null;
    }

    /**
     * Get Contact Parameters
     *
     * @param CentreonDB $db
     * @param array $parameters
     *
     * @return array
     * @throws PDOException
     */
    public function getContactParameters($db, $parameters = [])
    {
        $values = [];

        $queryParameters = '';
        if (is_array($parameters) && count($parameters)) {
            $queryParameters = 'AND cp_key IN ("';
            $queryParameters .= implode('","', $parameters);
            $queryParameters .= '") ';
        }

        $query = 'SELECT cp_key, cp_value '
            . 'FROM contact_param '
            . 'WHERE cp_contact_id = ' . $this->user_id . ' '
            . $queryParameters;

        $res = $db->query($query);
        while ($row = $res->fetch()) {
            $values[$row['cp_key']] = $row['cp_value'];
        }

        return $values;
    }

    /**
     * Set Contact Parameters
     *
     * @param CentreonDB $db
     * @param array $parameters
     *
     * @return null
     * @throws PDOException
     */
    public function setContactParameters($db, $parameters = [])
    {
        if (!count($parameters)) {
            return null;
        }
        $queryValues = [];
        $keys = array_keys($parameters);
        $deleteQuery = 'DELETE FROM contact_param WHERE cp_contact_id = :cp_contact_id AND cp_key IN( ';
        $queryValues[':cp_contact_id'] = $this->user_id;
        $queryKey ='';
        foreach ($keys as $key) {
            $queryKey .=' :cp_key'.$key.',';
            $queryValues[':cp_key'.$key] = $key;
        }
        $queryKey = rtrim($queryKey, ',');
        $deleteQuery .= $queryKey .' )';
        $stmt = $db->prepare($deleteQuery);
        $stmt->execute($queryValues);

        $insertQuery = 'INSERT INTO contact_param (cp_key, cp_value, cp_contact_id) VALUES '
            . '(:cp_key, :cp_value, :cp_contact_id)';
        $sth = $db->prepare($insertQuery);
        foreach ($parameters as $key => $value) {
            $sth->bindParam(':cp_key', $key, PDO::PARAM_STR);
            $sth->bindParam(':cp_value', $value, PDO::PARAM_STR);
            $sth->bindParam(':cp_contact_id', $this->user_id, PDO::PARAM_INT);
            $sth->execute();
        }
    }// FIXME no return

    /**
     * Get current Page
     *
     * @return int
     */
    public function getCurrentPage()
    {
        return $this->currentPage;
    }

    /**
     * Set current page
     *
     * @param int $currentPage
     * @return void
     */
    public function setCurrentPage($currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    /**
     * Get theme
     *
     * @return string
     */
    public function getTheme()
    {
        return $this->theme;
    }

    /**
     * Set theme
     *
     * @param string $theme
     * @return void
     */
    public function setTheme($theme): void
    {
        $this->theme = $theme;
    }

    /**
     * Get token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set token
     *
     * @param string $token
     * @return void
     */
    public function setToken($token): void
    {
        $this->token = $token;
    }

    /**
     * If the user has access to Rest API Configuration
     *
     * @return bool
     */
    public function hasAccessRestApiConfiguration()
    {
        return $this->restApi;
    }

    /**
     * If the user has access to Rest API Realtime
     *
     * @return bool
     */
    public function hasAccessRestApiRealtime()
    {
        return $this->restApiRt;
    }
}
