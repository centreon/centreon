@REQ_MON-159125
Feature: Event Logs visibility based on user roles

  Scenario: Restricted user cannot see event logs without resource access
    Given the admin user logs in
    When the admin creates host resources
    Then the admin user navigates to the Event Logs page
    And the admin user should see all event logs
    When the admin creates an access group for the restricted user
    Then the admin grants the restricted user event Monitoring through the Menu Access ACL
    And the admin user logs out
    Given the restricted user logs in
    When the restricted user navigates to the Event Logs page
    Then the event log page is accessible and restricted user should not see any event logs displayed

  Scenario: Restricted user can see event logs related to specific resources via ACL
    Given the admin user logs in
    When the admin creates host resources
    Then the admin creates an access group for the restricted user
    And the admin grants the restricted user event Monitoring through the Menu Access ACL
    And the admin assigns specific resources to the restricted user via Resource Access ACL
    And the admin user logs out
    Given the restricted user logs in
    When the restricted user navigates to the Event Logs page
    Then the restricted user should see only the event logs related to the assigned resources
