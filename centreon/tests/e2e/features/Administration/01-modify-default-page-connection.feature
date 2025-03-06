Feature: Modify Default Page Connection
  As a Centreon Web user
  I want to change the default connection page
  To access directly to the one I have chosen

  @TEST_MON-151598
  Scenario: Changing default page connection for an admin user
    Given an admin user is logged in a Centreon server
    And the user replaced the default page connection with Home > Dashboards
    When the admin user logs back to Centreon
    Then the active page is Home > Dashboards

  @TEST_MON-151599
  Scenario: Changing default page connection for a non admin user
    Given an non-admin user is logged in a Centreon server
    And the user has access to all menus
    And the user replaced the default page connection with Configuration > Hosts
    When the non-admin user logs back to Centreon
    Then the active page is Configuration > Hosts