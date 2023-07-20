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