Feature: Host listing access control
  As a Centreon user with a restricted resource ACL
  I want the hosts listing and its write actions to stay within my ACL
  So that I cannot read or change a host that was not granted to me

  Background:
    Given two hosts exist and only one of them is granted to a non-admin user

  Scenario: The listing only shows the granted host
    Given the non-admin user is logged in
    When the user opens the hosts listing
    Then only the granted host is listed

  Scenario: The toggle endpoint refuses a host outside the ACL
    Given the non-admin user is logged in
    When the user posts a toggle for the host it was not granted
    Then the endpoint answers that the object was not found
    And the activation of that host is unchanged

  Scenario: A bulk disable cannot reach a host outside the ACL
    Given the non-admin user is logged in
    When the user posts a bulk disable for the host it was not granted
    Then the activation of that host is unchanged
