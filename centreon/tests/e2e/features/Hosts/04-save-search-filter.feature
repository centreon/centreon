Feature: Save last search for filter
  As a Centreon user
  I want to have my last search in the page filter when I reopen the select2 after to have select an element
  To not retype the search but the listing is sorted after the edition and the save of a form

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-151739
  Scenario: Search a string in host template
    Given a search on the host template listing
    When the user changes page
    And the user goes back to the host template listing
    Then the search on the host template page is fill by the previous search

  @TEST_MON-151740
  Scenario: Search a string in traps
    Given a search on the traps listing
    When the user changes page
    And the user goes back to the traps listing
    Then the search on the traps page is fill by the previous search