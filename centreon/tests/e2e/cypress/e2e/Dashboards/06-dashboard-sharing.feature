Feature: As a Centreon User with dashboard administration rights,
  I need to list all dashboards and user/user group rights to each, and update access rights if needed
  so I can provision accesses or re-direct ownership if a user is not supposed to use Centreon anymore

Scenario: Accessing the sharing list of a dashboard
  Given a non-admin user who is on a list of shared dashboards
  When the user selects the share option on a dashboard
  Then the user is redirected to the sharing list of the dashboard
  And the creator of the dashboard is listed as its sole editor

Scenario: Adding a viewer user to a dashboard
  Given a non-admin user who has update rights on a dashboard
  When the editor user sets another user as a viewer on the dashboard
  Then the viewer user is listed as a viewer in the dashboard's share list
  When the viewer user logs in on the platform
  Then the dashboard is featured in the viewer user's dashboards library
  When the viewer user clicks on the dashboard
  Then the viewer user can visualize the dashboard's layout but cannot share it or update its properties

Scenario: Adding an editor user to a dashboard
  Given a non-admin user with the dashboard administrator role is logged in on a platform with dashboards
  When the dashboard administrator user sets another user as a second editor on a dashboard
  Then the second editor user is listed as an editor in the dashboard's share list
  When the second editor user logs in on the platform
  Then the dashboard is featured in the second editor user's dashboards library
  When the second editor user clicks on the dashboard
  Then the second editor can visualize the dashboard's layout and can share it or update its properties

Scenario: Adding read permissions of a dashboard to a contact group
  Given a non-admin editor user with update rights on a dashboard
  When the editor user sets read permissions on the dashboard to a contact group
  Then any member of the contact group has access to the dashboard in the dashboards library but cannot share it or update its properties

Scenario: Adding write permissions of a dashboard to a contact group
    Given a non-admin user who has update rights on a dashboard
    When the editor user sets write permissions on the dashboard to a contact group
    Then any member of the contact group has access to the dashboard in the dashboards library and can share it or update its properties