Feature: Custom views rotation
  As a Centreon user
  I want my custom views to rotate on screen at the interval I set
  So that a wall screen shows each of my views in turn

  @MON-214326
  Scenario: Rotation cycles through the user's custom views at the configured interval
    Given an admin user is logged in a Centreon server
    And the user owns two custom views "rbt-cv-rotate-1" and "rbt-cv-rotate-2"
    When the user sets the rotation to 10 seconds and applies it
    Then the displayed view switches to the other view within 10 seconds and back after another 10 seconds
    And the rotation interval is kept after the page is reloaded
