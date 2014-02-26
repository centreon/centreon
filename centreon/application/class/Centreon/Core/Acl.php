<?php

namespace Centreon\Core;

class Acl
{
    const ADD = 1;
    const DELETE = 2;
    const UPDATE = 4;
    const VIEW = 8;
    const ADVANCED = 16;

    private $routes;
    private $isAdmin;
    private $userId;

    /**
     * Constructor
     *
     * @param \Centreon\Core\User $userId
     */
    public function __construct($user)
    {
        $this->userId = $user->getId();
        $this->isAdmin = $user->isAdmin();
    }

    /**
     * Checks whether or not a flag is set
     *
     * @param int $values
     * @param int $flag
     * @return bool
     */
    public static function isFlagSet($values, $flag)
    {
        return (($values & $flag) === $flag);
    }

    /**
     * Get user ACL
     *
     * @param string $route
     */
    public function getUserAcl($route)
    {
        static $rules = null;

        if (is_null($rules)) {
            $rules = array();
            $db = Di::getDefault()->get('db_centreon');
            $stmt = $db->prepare(
                "SELECT DISTINCT acl_level, url 
                FROM acl_menu_menu_relations ammr, acl_group_menu_relations agmr, menus m
                WHERE ammr.acl_menu_id = agmr.acl_menu_id
                AND ammr.menu_id = m.menu_id
                AND agmr.acl_group_id IN (
                    SELECT acl_group_id 
                    FROM acl_group_contacts_relations agcr
                    WHERE agcr.contact_contact_id = :contactid
                    UNION
                    SELECT acl_group_id
                    FROM acl_group_contactgroups_relations agcgr, contactgroup_contact_relation ccr
                    WHERE agcgr.cg_cg_id = ccr.contactgroup_cg_id
                    AND ccr.contact_contact_id = :contactid
                ) "
            );
            $stmt->bindParam(':contactid', $this->userId);
            $stmt->execute();
            $rows = $stmt->fetchAll();
            $aclFlag = 0;
            foreach ($rows as $row) {
                if (!isset($rules[$row['url']])) {
                    $rules[$row['url']] = 0;
                }
                $rules[$row['url']] = $rules[$row['url']] | $row['acl_level'];
            }
        }
        foreach ($rules as $uri => $acl) {
            if (strstr($route, $uri)) {
                return $acl;
            }
        }
    }

    /**
     * Check whether user is allowed to access route
     *
     * @param array $data
     * @return bool
     */
    public function routeAllowed($data)
    {
        if ($this->isAdmin) {
            return true;
        }
        if ($data['route'] && $data['acl']) {
            return self::isFlagSet($this->getUserAcl($data['route']), $data['acl']);
        }
        return true;
    }

    /**
     * Convert ACL flags
     *
     * @return int
     */
    public static function convertAclFlags($aclFlags)
    {
        $flag = 0;
        foreach ($aclFlags as $flag) {
            switch (strtolower($flag)) {
                case "add": 
                    $f = self::ADD;
                    break;
                case "delete":
                    $f = self::DELETE;
                    break;
                case "update":
                    $f = self::UPDATE;
                    break;
                case "view":
                    $f = self::VIEW;
                    break;
            }
            $flag = $flag | $f;
        }
        return $flag;
    }
}
