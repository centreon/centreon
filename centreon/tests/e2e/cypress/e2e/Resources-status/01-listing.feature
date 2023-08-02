Feature: List Resources
  As a user
  I want to list the available Resources and filter them
  So that I can handle associated problems quickly and efficiently

  Scenario: Accessing the page for the first time
    Then the unhandled problems filter is selected
    And only non-ok resources are displayed
