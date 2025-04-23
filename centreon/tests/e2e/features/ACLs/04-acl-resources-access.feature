@REQ_MON-37971
Feature: ACL Resources Access administration
  As a Centreon administrator
  I want to administrate Resources Access
  To give access to Centreon objects to users according their role in the company

  Background:
    Given I am logged in a Centreon server
    And three ACL access groups including non admin users exist

  @TEST_MON-37869
  Scenario: Creating Resources Access linked to several access groups
    When I add a new Resources access linked with two groups
    Then the Resources access is saved with its properties
    And only chosen linked access groups display the new Resources access in Authorized information tab

  @TEST_MON-37873
  Scenario: Remove one access group from Resources access
    Given one existing Resources access linked with two access groups
    When I remove one access group
    Then link between access group and Resources access must be broken

  @TEST_MON-37871
  Scenario: Duplicate one existing Resources access record
    Given one existing Resources access
    When I duplicate the Resources access
    Then a new Resources access record is created with identical properties except the name

  @TEST_MON-37870
  Scenario: Modify one existing Resources access record
    Given one existing enabled Resources access record
    When I modify some properties such as name, description, comments or status
    Then the modifications are saved

  @TEST_MON-37872
  Scenario: Delete one existing Resources access record
    Given one existing Resources access
    When I delete the Resources access
    Then the Resources access record is not visible anymore in Resources Access page
