Feature: First test
  As a Centreon Administrator
  I want to visit Dashboard page
  To create a dashboard

  Scenario: Access to dashboard page
    Given the administrator is logged in
    When the admin user visits dashboard page
    Then the admin user could create a new dashboard