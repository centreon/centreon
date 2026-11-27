Feature: ACL enforcement
  As a Centreon administrator
  I want the menus, resources and actions access of an access group to be enforced in the interface and the APIs
  So that a restricted user only sees and acts on what the access group grants

  @MON-214314
  Scenario: A user restricted by a menu access sees only the granted menu and the API refuses the others
    Given a non-admin contact rbt-acl-user in the access group rbt-acl-group with no menu access
    When the administrator creates the menu access rbt-acl-menus granting only Monitoring > Resources Status to rbt-acl-group
    And the administrator reloads the ACL of rbt-acl-user
    Then rbt-acl-user sees only the Monitoring menu and opens Resources Status
    And the hosts configuration page is refused to rbt-acl-user
    And the API answers 403 on GET /configuration/hosts and 200 on GET /monitoring/resources for rbt-acl-user

  @MON-214315
  Scenario: A user restricted by a resources access sees only the resources of the granted host and service groups, in Resources Status and in a shared dashboard
    Given rbt-acl-user in rbt-acl-group with the menu access rbt-acl-menus
    And the monitored hosts rbt-acl-host-in, rbt-acl-host-out, rbt-acl-sg-host and rbt-acl-late-host
    And the host group rbt-acl-hg holding only rbt-acl-host-in
    And the service group rbt-acl-sg holding the service rbt-acl-svc-granted of rbt-acl-sg-host but not its service rbt-acl-svc-hidden
    When the administrator creates the resources access rbt-acl-resources granting the host group rbt-acl-hg and the service group rbt-acl-sg to rbt-acl-group
    And the administrator reloads the ACL of rbt-acl-user
    Then the centreon_acl table of rbt-acl-group holds rbt-acl-host-in, rbt-acl-sg-host and the service rbt-acl-svc-granted only
    And rbt-acl-user sees rbt-acl-host-in, rbt-acl-sg-host and rbt-acl-svc-granted but not rbt-acl-host-out, rbt-acl-late-host or rbt-acl-svc-hidden in Resources Status and in the resources API
    And the administrator sees every rbt-acl resource
    When the administrator adds rbt-acl-late-host to the host group rbt-acl-hg and reloads the ACL of rbt-acl-user
    Then rbt-acl-user sees rbt-acl-late-host in Resources Status without any export
    When the administrator grants the Dashboards Viewer menu to rbt-acl-group
    And the administrator shares with rbt-acl-user as Viewer the dashboard rbt-acl-dashboard whose status grid and resource table show rbt-acl-host-in and rbt-acl-host-out and whose metrics graph shows rbt_value of rbt-acl-sg-host
    Then rbt-acl-user sees only rbt-acl-host-in in the status grid and in the resource table
    And the metrics graph of rbt-acl-user draws only the rbt_value curve of rbt-acl-svc-granted

  @MON-214316
  Scenario: A user without the acknowledge action cannot acknowledge a host until the action is granted
    Given rbt-acl-user sees the Down host rbt-acl-host-in in Resources Status
    And no actions access is linked to rbt-acl-group
    Then the Acknowledge action is disabled for rbt-acl-user and the acknowledge API answers 401
    When the administrator creates the actions access rbt-acl-actions granting only Acknowledge a host to rbt-acl-group
    And the administrator reloads the ACL of rbt-acl-user
    Then the acknowledge API answers 204 for rbt-acl-user and rbt-acl-host-in is acknowledged
    And the downtime action stays disabled for rbt-acl-user

  @MON-214317
  Scenario: An ACL change is applied by the centAcl cron within a minute without a manual reload
    Given rbt-acl-user sees rbt-acl-host-in and not rbt-acl-host-out in Resources Status
    And the centAcl cron is not running and ran less than two minutes ago
    When the administrator adds rbt-acl-host-out to the resources access rbt-acl-resources without reloading the ACL
    Then the resources access rbt-acl-resources is flagged as changed
    And within ninety seconds the cron has run again and the flag is cleared
    And the centreon_acl table and Resources Status show both hosts to rbt-acl-user
