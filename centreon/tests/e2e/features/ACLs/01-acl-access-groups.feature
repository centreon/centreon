@REQ_MON-37968
Feature: AclAccessGroups
  As a Centreon administrator
  I want to administrate ACL access groups
  To give access to Centreon pages to users according their role in the company

  Background:
    Given I am logged in a Centreon server

  @TEST_MON-37848
  Scenario: Creating ACL access group with linked contacts
    Given one contact group exists including two non admin contacts
    When the access group is saved with its properties
    And a menu access is linked with this group
    Then all linked users have the access list group displayed in Centreon authentication tab

  @TEST_MON-37850
  Scenario: Creating ACL access group with linked contact group
    Given a new access group with a linked contact group
    When the access group is saved with its properties
    Then the contact group has the access group displayed in Relations information

  @TEST_MON-37849
  Scenario: Modify ACL access group properties
    Given one existing ACL access group
    When I modify its properties
    Then all modified properties are updated

  @TEST_MON-37847
  Scenario: Duplicate ACL access group
    Given one existing ACL access group
    When I duplicate the access group
    Then a new access group with identical properties is created

  @TEST_MON-37852
  Scenario: Delete ACL access group
    Given one existing ACL access group
    When I delete the access group
    Then it does not exist anymore

  @TEST_MON-37851
  Scenario: Disable ACL access group
    Given one existing enabled ACL access group
    When I disable it
    Then its status is modified
