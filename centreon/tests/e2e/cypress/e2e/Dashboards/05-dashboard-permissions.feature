Feature: As a Centreon User with dashboard administration rights,
  I need to list all dashboards and user/user group rights to each, and update access rights if needed
  so I can provision accesses or re-direct ownership if a user is not supposed to use Centreon anymore

Scenario: Accessing all dashboards as an admin user
  Given an admin user on a platform with dashboards
  When the admin user accesses the dashboard library in list mode
  Then a list of all the dashboards of the platform is displayed