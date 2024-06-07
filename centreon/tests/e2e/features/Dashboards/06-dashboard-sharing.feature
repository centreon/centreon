@REQ_MON-18516
Feature: Sharing a dashboard
  As a Centreon User with dashboard edition rights,
  I need to be able to share dashboards to other users with either read or write access,
  So that these users may in turn consult, update or share these dashboards

  @TEST_MON-22186
  Scenario: Accessing the sharing list of a dashboard
    Given a non-admin user who is in a list of shared dashboards
    When the user selects the share option on a dashboard
    Then the user is redirected to the sharing list of the dashboard
    And the creator of the dashboard is listed as its sole editor

  @TEST_MON-22187
  Scenario: Adding a viewer user to a dashboard
    Given a non-admin user who has update rights on a dashboard
    When the editor user sets another user as a viewer on the dashboard
    Then the viewer user is listed as a viewer in the dashboard's share list
    When the viewer user logs in on the platform
    Then the dashboard is featured in the viewer user's dashboards library
    When the viewer user clicks on the dashboard
    Then the viewer user can visualize the dashboard's layout but cannot share it or update its properties

  @TEST_MON-22188
  Scenario: Adding an editor user to a dashboard
    Given a non-admin user with the dashboard administrator role is logged in on a platform with dashboards
    When the dashboard administrator user sets another user as a second editor on a dashboard
    Then the second editor user is listed as an editor in the dashboard's share list
    When the second editor user logs in on the platform
    Then the dashboard is featured in the second editor user's dashboards library
    When the second editor user clicks on the dashboard
    Then the second editor can visualize the dashboard's layout and can share it or update its properties

  @TEST_MON-22184
  Scenario: Adding read permissions of a dashboard to a contact group
    Given a non-admin editor user with creator rights on a dashboard
    When the editor user sets read permissions on the dashboard to a contact group
    Then any member of the contact group has access to the dashboard in the dashboards library but cannot share it or update its properties

  @TEST_MON-22190
  Scenario: Adding write permissions of a dashboard to a contact group
    Given a non-admin editor user who has creator rights on a dashboard
    When the editor user sets write permissions on the dashboard to a contact group
    Then any member of the contact group has access to the dashboard in the dashboards library and can share it or update its properties

  @TEST_MON-22189
  Scenario: Overriding read permissions of a dashboard on a contact group's certain user
    Given a non-admin editor user who has update rights on a dashboard with read permissions given to a contact group
    When the editor user sets write permissions on the dashboard to a specific user of the contact group
    Then the user whose permissions have been overridden can perform write operations on the dashboard
    Then the other users of the contact group still have read-only permissions on the dashboard

  @TEST_MON-22185 @ignore
  Scenario: Add new users to the share list as a new dashboard editor
    Given a dashboard featuring a dashboard administrator as editor, and three users who are not part of the dashboard's share list
    When the admin user appoints one of the users as an editor
    Then the newly appointed editor user can appoint another user as an editor
    Then the newly appointed editor user can appoint another user as a viewer