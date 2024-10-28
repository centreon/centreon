Feature: NonAdminContactCreation
  As a Centreon admin user
  I want to create a non admin contact
  New contact is able to log in Centreon Web

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-151725
  Scenario: Basic operations on contacts
    When the admin user creates a non admin contact
    And the admin user duplicates this contact
    And the admin delete this contact
    Then the duplicated contact is displayed in the user list
    And the deleted contact is not displayed in the user list
    And the admin can logg in Centreon Web with the duplicated contact account
