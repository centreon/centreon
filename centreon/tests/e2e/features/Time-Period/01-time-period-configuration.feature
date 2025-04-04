Feature: Time period Configuration
  As a Centreon user
  I want to configure various types of time periods
  To avoid useless monitoring checks during company closing

  Background:
    Given a user is logged in Centreon

  @TEST_MON-162178
  # jours à exclure : 1er janvier, 1er mai, 14 juillet, 25 décembre
  Scenario: Time period excluding holidays
    When a user creates a time period with separated holidays dates excluded
    Then all properties of my time period are saved

  @TEST_MON-162179
  # période à exclure : du 1er au 31 août
  Scenario: Time period excluding a range of dates
    When a user creates a time period with a range of dates to exclude
    Then all properties of my time period are saved with the exclusions

  @TEST_MON-162180
  Scenario: Duplicating an existing time period
    Given an existing time period
    When a user duplicates the time period
    Then a new time period is created with identical properties except the name

  @TEST_MON-162181
  Scenario: Delete an existing time period
    Given an existing time period
    When a user deletes the time period
    Then the time period disappears from the time periods list
