Feature: Time period Configuration
  As a Centreon user
  I want to configure various types of time periods
  To avoid useless monitoring checks during company closing

  Background:
    Given a user is logged in Centreon

  @MON-162178
  # jours à exclure : 1er janvier, 1er mai, 14 juillet, 25 décembre
  @MON-205082
  Scenario: Time period excluding holidays
    When a user creates a time period with separated holidays dates excluded
    Then all properties of my time period are saved

  @MON-162179
  # période à exclure : du 1er au 31 août
  @MON-205081
  Scenario: Time period excluding a range of dates
    When a user creates a time period with a range of dates to exclude
    Then all properties of my time period are saved with the exclusions

  @MON-162180
  Scenario: Duplicating an existing time period
    Given an existing time period
    When a user duplicates the time period
    Then a new time period is created with identical properties except the name

  @MON-162181
  Scenario: Delete an existing time period
    Given an existing time period
    When a user deletes the time period
    Then the time period disappears from the time periods list

  @MON-200035
  Scenario: The listing loads its rows over AJAX
    Given several time periods exist
    When the user navigates to the time periods listing
    Then the listing table is displayed with time period rows

  @MON-200035
  Scenario: Searching filters the listing down to the matching time period
    Given several time periods exist
    When the user navigates to the time periods listing
    And the user searches for a specific time period
    Then only the matching time period is displayed

  @MON-200035
  Scenario: The listing reports its total count in the pagination
    Given several time periods exist
    When the user navigates to the time periods listing
    Then the pagination info shows the total count

  @MON-200035
  Scenario: Clicking a time period name opens the form in the side panel
    Given several time periods exist
    When the user navigates to the time periods listing
    And the user clicks on a time period name
    Then the time period form opens in the side panel

  @MON-200035
  Scenario: The search term survives navigating away and back
    Given several time periods exist
    When the user navigates to the time periods listing
    And the user searches for a specific time period
    And the user navigates back to the time periods listing
    Then the search field still contains the search term
