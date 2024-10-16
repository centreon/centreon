Feature: Delete a Telegraf configuration
  As a Centreon user
  I want to visit the Agents Configuration page
  To delete the Telegraf agent configuration

  Scenario: Delete a telegraf configuration
    Given a non-admin user is in the Agents Configuration page
    And a telegraf configuration is already created
    When the user deletes the telegraf configuration
    And the user confirms on the pop-up
    Then the telegraf configuration is no longer displayed in the listing page

  Scenario: Cancel a deletion pop-up
    Given a non-admin user is in the Agents Configuration page
    And a telegraf configuration is already created
    When the user deletes the telegraf configuration
    And the user cancel on the pop-up
    Then the telegraf configuration is still displayed in the listing page