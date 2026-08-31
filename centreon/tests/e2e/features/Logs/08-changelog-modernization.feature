@REQ_MON-152872
Feature: Modern changelog listing with infinite scroll and inline diff
  As a Centreon admin
  I want to browse configuration changes with infinite scroll
  And see inline diffs without leaving the page

  Background:
    Given a user is logged in Centreon

  @MON-200050
  Scenario: Changelog loads with infinite scroll
    Given a configuration change has been recorded
    When the user navigates to the changelog page
    Then the changelog listing is displayed
    And no pagination controls are shown
    And a scroll info counter is displayed

  @MON-200050
  Scenario: Changelog search filters by object name
    Given a configuration change has been recorded
    When the user navigates to the changelog page
    And the user searches for that object name in the changelog
    Then only matching changelog entries are displayed

  @MON-200050
  Scenario: Changelog object type filter works
    Given a configuration change has been recorded
    When the user navigates to the changelog page
    And the user selects the host category object type filter
    Then only entries of that type are displayed

  @MON-200050
  Scenario: Inline diff expand shows field changes
    Given a configuration change has been recorded
    When the user navigates to the changelog page
    And the user clicks the expand button on the Added entry
    Then a diff panel appears below the row
    And the diff shows the recorded field name and value

  @MON-200050
  Scenario: Inline diff collapse hides the panel
    Given a configuration change has been recorded
    When the user navigates to the changelog page
    And the user expands the Added entry
    And the user clicks the expand button again
    Then the diff panel is removed

  @MON-200050
  Scenario: Disabled entries cannot be expanded
    Given a disabled configuration change has been recorded
    When the user navigates to the changelog page
    Then the Disabled entry expand button is grayed out and not clickable

  @MON-200050
  Scenario: Clicking an object name opens the timeline detail page
    Given a configuration change has been recorded
    When the user navigates to the changelog page
    And the user clicks on the object name link
    Then the timeline detail page is displayed
    And a back button returns to the changelog listing
