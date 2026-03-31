Feature: Modern changelog listing with infinite scroll and inline diff
    As a Centreon admin
    I want to browse configuration changes with infinite scroll
    And see inline diffs without leaving the page

  Background:
    Given a user is logged in Centreon

  Scenario: Changelog loads with infinite scroll
    When the user navigates to the changelog page
    Then the changelog listing is displayed
    And no pagination controls are shown
    And a scroll info counter is displayed

  Scenario: Changelog search filters by object name
    When the user navigates to the changelog page
    And the user searches for an object name in the changelog
    Then only matching changelog entries are displayed

  Scenario: Changelog object type filter works
    When the user navigates to the changelog page
    And the user selects an object type filter
    Then only entries of that type are displayed

  Scenario: Inline diff expand shows field changes
    When the user navigates to the changelog page
    And an Added or Changed entry exists
    And the user clicks the expand button on that entry
    Then a diff panel appears below the row
    And the diff shows field names and values

  Scenario: Inline diff collapse hides the panel
    When the user navigates to the changelog page
    And the user expands a changelog entry
    And the user clicks the expand button again
    Then the diff panel is removed

  Scenario: Disabled entries cannot be expanded
    When the user navigates to the changelog page
    And an Enabled or Disabled entry exists
    Then its expand button is grayed out and not clickable

  Scenario: Clicking an object name opens the timeline detail page
    When the user navigates to the changelog page
    And the user clicks on an object name link
    Then the timeline detail page is displayed
    And a back button returns to the changelog listing
