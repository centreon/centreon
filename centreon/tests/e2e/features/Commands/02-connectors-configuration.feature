Feature: Configuration of a connector
  As a Centreon user
  I want to manipulate a connector
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-160507
  Scenario: Create a new connector
    When the user creates a connector
    Then the connector is displayed in the list

  @TEST_MON-160508
  Scenario: Change the properties of a connector
    When the user changes the properties of a connector
    Then the properties are updated

  @TEST_MON-160509
  Scenario: Duplicate one existing connector
    When the user duplicates a connector
    Then the new connector has the same properties

  @TEST_MON-160510
  Scenario Outline: Change status of one existing connector
    When the user updates the status of a connector to '<type>'
    Then the new connector is updated with '<type>' status

    Examples:
      | type      |
      | Disabled  |
      | Enabled   |

  @TEST_MON-160511
  Scenario: Delete one existing connector
    When the user deletes a connector
    Then the deleted connector is not displayed in the list