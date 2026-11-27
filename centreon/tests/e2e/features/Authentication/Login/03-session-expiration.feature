Feature: Session expiration
  As a Centreon administrator
  I want an idle session to expire after the Sessions Expiration Time
  So that an unattended browser does not stay logged in

  @MON-214319
  Scenario: A session expires after the Sessions Expiration Time and the user must log in again
    Given a Centreon platform whose Sessions Expiration Time is set to 1 minute
    And a local user logged in
    When the user makes no request for more than 1 minute
    Then the next page the user opens is the login page
    And the user stays logged in while the page keeps polling
