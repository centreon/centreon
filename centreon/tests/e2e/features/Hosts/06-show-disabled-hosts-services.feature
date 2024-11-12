Feature: Add a filter to display the services of disabled hosts
  As a Centreon admin
  I want to display the services of disabled hosts
  To improve my configuration

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-152637
  Scenario: Add a checkbox to show the services of disabled hosts
    Given a host with configured services
    And the host is disabled
    When the user visit the menu of services configuration
    Then the services of disabled hosts are not displayed
    When the user activates the visibility filter of disabled hosts
    And the user clicks on the Search button
    Then the services of disabled hosts are displayed