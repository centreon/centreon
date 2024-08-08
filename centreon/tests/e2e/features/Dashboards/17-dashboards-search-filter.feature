@REQ_MON-146685
Feature: Dashboards Search Filter
  As a Centreon User with dashboard edition rights,
  I need to search for some existed dashboards using a filter

  @TEST_MON-146685
  Scenario: Filtering dashboards with the right search word
    Given a Centreon User with dashboard edition rights on dashboard listing page
    When the user sets a right value in the search filter
    Then the dashboards that respect the filter are displayed

  @TEST_MON-146685
  Scenario: Filtering dashboards list with the wrong filter
    Given a Centreon User with dashboard edition rights on dashboard listing page
    When the user sets the wrong value in the search filter
    Then no dashboards records are returned

  
