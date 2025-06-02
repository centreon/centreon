Feature: Download Resource Status Data as CSV
  As a Centreon user
  I want to export the current status of resources
  So that I can review or process them externally

  @TEST_MON-171355
  Scenario: Export resource status to CSV with all columns and all pages selected
    Given an admin user is logged in and redirected to the Resource Status page
    When the admin user clicks the Export button
    Then a CSV file should be downloaded
    And the CSV file should contain the correct headers and the expected data

  @TEST_MON-171355
  Scenario: Export resource status to CSV using only the currently visible columns and pages
    Given an admin user is logged in and redirected to the Resource Status page
    When the admin user unchecks some columns in the table settings
    And the admin user exports only visible columns and pages
    Then a CSV file should be downloaded
    And the CSV file should contain the updated headers and the expected data