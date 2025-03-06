@REQ_MON-152872
Feature: Commands changes log
  As a Centreon user
  I want to do some changes on commands
  To check if the changes are inserted in to the log page

  @TEST_MON-153465
  Scenario Outline: A call via APIv2 to the endpoint "Add" a "<type>" command insert logs changes
    Given a user is logged in a Centreon server via APIv2
    When a call to the endpoint "Add" a "<type>" command is done via APIv2
    Then a new "<type>" command is displayed on the "<type>" commands page
    And a new "Added" ligne of log is getting added to the page Administration > Logs
    And the informations of the log are the same as those of the "<type>" command
    Examples:
      | type           |
      | NOTIFICATION   |
      | CHECK          |
      | MISCELLANEOUS  |
      | DISCOVERY      |