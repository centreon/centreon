Feature: As a Centreon User with dashboard administration rights,
  I need to list all dashboards and user/user group rights to each, and update access rights if needed
  so I can provision accesses or re-direct ownership if a user is not supposed to use Centreon anymore

Scenario: Accessing the sharing list of a dashboard
    Given a non-admin user who is on a list of shared dashboards
    When the user selects the share option on a dashboard
    Then the user is redirected to the sharing list of the dashboard
    And the creator of the dashboard is listed as its sole editor