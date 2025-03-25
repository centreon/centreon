Feature: Database partitioning
  As a Centreon user
  I want to clean database tables quickly
  To keep it easy to maintain

  @TEST_MON-161527
  Scenario: Database partitioning informations
    Given a user is logged in a Centreon server
    When the user visits the database informations page
    Then partitioning tables are displayed
    And more general information on the state of health of the databases is also present