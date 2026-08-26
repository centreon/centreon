Feature: Configuration of a connector
  As a Centreon user
  I want to manipulate a connector
  To see if all simples manipulations work

  Background:
    Given an admin user is logged in a Centreon server

  @MON-160507
  Scenario: Create a new connector
    When the user creates a connector
    Then the connector is displayed in the list

  @MON-160508
  Scenario: Change the properties of a connector
    When the user changes the properties of a connector
    Then the properties are updated

  @MON-160509
  Scenario: Duplicate one existing connector
    When the user duplicates a connector
    Then the new connector has the same properties

  @MON-206501
  Scenario: Filter the listing with the search field
    Given a connector with a distinctive description and command line exists
    When the user searches the listing by name, by description and by command line
    Then each search returns only that connector

  @MON-206501
  Scenario: A read-only user is offered no write control
    When a user with read-only rights displays the connectors
    Then no write control is offered and the server refuses a forged status change

  @MON-160510
  Scenario Outline: Change status of one existing connector
    When the user updates the status of a connector to '<type>'
    Then the new connector is updated with '<type>' status

    Examples:
      | type      |
      | Disabled  |
      | Enabled   |

  @MON-206501
  Scenario: A failed status change is reported to the user
    When the status change of a connector fails on the server
    Then the toggle returns to its previous state and an error is displayed

  @MON-206501
  Scenario: A status change refused inside a 200 response is not shown as applied
    When the server answers the status change with a 200 that is not a success
    Then the toggle returns to its previous state and an error is displayed

  @MON-206501
  Scenario: A malformed listing response is reported, not read as an empty page
    When the listing endpoint answers with a 200 that carries no rows
    Then the listing reports an error instead of an empty page

  @MON-206501
  Scenario: Duplicating a connector honours the typed count
    When the user duplicates a connector three times from the listing
    Then the three copies are listed

  @MON-206501
  Scenario: A typed duplication count survives a listing re-render
    When the user types a duplication count and the listing re-renders
    Then the typed count is still there

  @MON-206501
  Scenario: A stored page past the end falls back to a page that holds rows
    When the listing is opened on a page that no longer exists
    Then the first page is displayed with its rows

  @MON-160511
  Scenario: Delete one existing connector
    When the user deletes a connector
    Then the deleted connector is not displayed in the list
