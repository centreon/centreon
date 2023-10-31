@ignore
@REQ_MON-24183
Feature: Get notification rules
  As a Centreon user with access to the notification rules page
  I need to show this list of notification rules

  Background:
    Given a user with access to the notification rules page
    And the user is on the notification rules page
    And a minimal one of notification rule is created

  Scenario: Getting notification rules
    When the user arrived on the listing notification rules page
    Then a minimal one line from the listing displays notification rule
