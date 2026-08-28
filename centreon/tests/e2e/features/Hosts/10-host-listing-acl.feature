Feature: Host listing access control
  As a Centreon user with a restricted resource ACL
  I want the hosts listing and its write actions to stay within my ACL
  So that I cannot read or change a host that was not granted to me

  Background:
    Given two hosts exist and only one of them is granted to non-admin users

  Scenario: The listing only shows the granted host
    Given the non-admin user is logged in
    When the user opens the hosts listing
    Then only the granted host is listed

  Scenario: The toggle endpoint refuses a host outside the ACL
    Given the non-admin user is logged in
    When the user posts a toggle for the host it was not granted
    Then the endpoint answers that the object was not found
    And the activation of that host is unchanged

  @MON-200026
  Scenario: A bulk disable only changes hosts inside the ACL
    Given the non-admin user is logged in
    When the user posts a bulk disable for both hosts
    Then the granted host is disabled
    And the activation of that host is unchanged

  @MON-200026
  Scenario: A mass change only carries hosts inside the ACL
    Given the non-admin user is logged in
    When the user opens a mass change for both hosts
    Then only the granted host is carried into the mass change

  Scenario: A read-only user gets the row stripped of its write controls
    Given the read-only user is logged in
    When the user opens the hosts listing
    Then the row toggle is disabled and carries no handler
    And the row still links to the services of that host
