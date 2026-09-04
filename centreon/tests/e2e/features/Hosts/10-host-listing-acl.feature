@REQ_MON-207948
Feature: Host listing access control
  As a Centreon user with a restricted resource ACL
  I want the hosts listing and its write actions to stay within my ACL
  So that I cannot read or change a host that was not granted to me

  Background:
    Given two hosts exist and only one of them is granted to non-admin users

  # Cloud: identical
  Scenario: The listing only shows the granted host
    Given the non-admin user is logged in
    When the user opens the hosts listing
    Then only the granted host is listed

  # Cloud: identical
  Scenario: A disable posted for a host outside the ACL changes nothing
    Given the non-admin user is logged in
    When the user posts a disable for the host it was not granted
    Then the activation of that host is unchanged

  # Cloud: identical
  Scenario: A bulk disable only changes hosts inside the ACL
    Given the non-admin user is logged in
    When the user posts a bulk disable for both hosts
    Then the granted host is disabled
    And the activation of that host is unchanged

  # Cloud: identical
  Scenario: A mass change only carries hosts inside the ACL
    Given the non-admin user is logged in
    When the user opens a mass change for both hosts
    Then only the granted host is carried into the mass change

  # Cloud: identical
  Scenario: A read-only user cannot bulk disable at all
    Given the read-only user is logged in
    When the user posts a bulk disable for the granted host
    Then the granted host is still enabled
