Feature: NonAdminContactCreation
  As a Centreon admin user
  I want to create a non admin contact
  New contact is able to log in Centreon Web

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-151725
  Scenario: Non-admin Contact Management Operations
    When the admin user creates a non admin contact
    And the admin user duplicates the newly created non-admin contact
    And the admin user deletes the original non-admin contact
    Then the duplicated contact is displayed in the user list
    And the deleted contact should not be visible in the user list
    And the admin can log in to Centreon Web with the duplicated contact account