Feature: AWIE import and export pages
  As a Centreon user
  I want the AWIE import and export pages to be reachable
  So that I can import and export the platform configuration

  Background:
    Given an admin user is logged in

  Scenario: the export page is reachable
    When the user opens the AWIE export page
    Then the export form is displayed

  Scenario: the import page is reachable
    When the user opens the AWIE import page
    Then the import form is displayed
