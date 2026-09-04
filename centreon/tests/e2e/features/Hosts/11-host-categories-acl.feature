Feature: Host categories access control
  As a Centreon user with a restricted host-category ACL
  I want the host categories listing and its write actions to stay within my ACL
  So that I cannot read or change a category that was not granted to me

  Background:
    Given two host categories exist and only one is granted to the non-admin user

  Scenario: The listing only shows the granted category
    Given the non-admin host-category user is logged in
    When the user opens the host categories listing
    Then only the granted host category is listed

  Scenario: A disable posted for a category outside the ACL changes nothing
    Given the non-admin host-category user is logged in
    When the user posts a disable for the category it was not granted
    Then the activation of that category is unchanged

  Scenario: A disable posted for the granted category flips it
    Given the non-admin host-category user is logged in
    When the user posts a disable for the granted category
    Then the granted category is disabled

  Scenario: A bulk disable only changes categories inside the ACL
    Given the non-admin host-category user is logged in
    When the user posts a bulk disable for both categories
    Then only the granted category is disabled

  Scenario: A delete posted for a category outside the ACL keeps it
    Given the non-admin host-category user is logged in
    When the user posts a delete for the category it was not granted
    Then the denied category still exists

  Scenario: A read-only user opens a category in view mode with no script error
    Given the read-only host-category user is logged in
    When the user opens the granted category in view mode
    Then the view form renders and the severity fields are collapsed
