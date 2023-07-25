Feature: As a Centreon User with dashboard administration rights,
  I need to list all dashboards and user/user group rights to each, and update access rights if needed
  so I can provision accesses or re-direct ownership if a user is not supposed to use Centreon anymore

Scenario: Accessing all dashboards as an admin user
  Given an admin user is logged in on a platform with dashboards
  When the admin user accesses the dashboards library
  Then the admin user can view all the dashboards configured on the platform
  When the admin user clicks on a dashboard
  Then the admin user is redirected to the detail page for this dashboard
  And the admin user is allowed to access the edit mode for this dashboard
  And the admin user is allowed to update the dashboard's properties

Scenario: Creating a new dashboard as an admin user
  Given an admin user on the dashboards library
  When the admin user creates a new dashboard
  Then the dashboard is created and is noted as the creation of the admin user

Scenario: Accessing all dashboards as a non-admin dashboard administrator user
  Given a non-admin user with the dashboard administrator role is logged in on a platform with dashboards
  When the dashboard administrator user accesses the dashboards library
  Then the dashboard administrator user can consult all the dashboards configured on the platform
  When the dashboard administrator user clicks on a dashboard
  Then the dashboard administrator user is redirected to the detail page for this dashboard
  And the dashboard administrator user is allowed to access the edit mode for this dashboard
  And the dashboard administrator user is allowed to update the dashboard's properties

Scenario: Creating a new dashboard as a non-admin dashboard administrator user
  Given a non-admin user with the administrator role on the dashboard feature
  When the dashboard administrator user creates a new dashboard
  Then the dashboard is created and is noted as the creation of the dashboard administrator user

Scenario: Accessing the list of shared dashboards as a dashboard editor
  Given a non-admin user with the dashboard editor role is logged in on a platform with dashboards
  When the dashboard editor user accesses the dashboards library
  Then a list of the dashboards the dashboard editor user has access to is displayed
  When the dashboard editor user clicks on a dashboard
  Then the dashboard editor user is redirected to the detail page for this dashboard
  And the dashboard editor user is allowed to access the edit mode for this dashboard
  And the dashboard editor user is allowed to update the dashboard's properties

Scenario: Creating a new dashboard as a non-admin dashboard editor user
  Given a non-admin user with the editor role on the dashboard feature
  When the dashboard editor user creates a new dashboard
  Then the dashboard is created and is noted as the creation of the dashboard editor user

Scenario: Accessing the list of shared dashboards as a dashboard viewer
  Given a non-admin user with the dashboard viewer role is logged in on a platform with dashboards
  When the dashboard viewer user accesses the dashboards library
  Then a list of the dashboards the dashboard viewer user has access to is displayed
  When the dashboard viewer user clicks on a dashboard
  Then the dashboard viewer user is redirected to the detail page for this dashboard
  And the dashboard viewer user does not have access to any update or share-related options on a dashboard

Scenario: Inability to create a new dashboard as a non-admin dashboard viewer
  Given a non-admin user with the viewer role on the dashboard feature
  When the dashboard viewer accesses the dashboards library
  Then the option to create a new dashboard is not displayed