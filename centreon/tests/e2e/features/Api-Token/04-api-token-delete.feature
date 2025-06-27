@REQ_MON-21278
Feature: Delete Authentication Token
  As an administrator
  I want to delete an Authentication token using the "delete token" icon
  So that I can manage the tokens effectively

  Background:
    Given I am logged in as an administrator
    And Authentication tokens with predefined details are created
    And I am on the Authentication tokens page

  @TEST_MON-36703
  Scenario: Delete Authentication Token with confirmation
    When I locate the Authentication token to delete
    And I click on the "delete token" icon for that token
    And I confirm the deletion in the confirmation dialog
    Then the token is deleted successfully

  @TEST_MON-38499
  Scenario: Discard deletion of an Authentication Token
    When I locate the Authentication token to delete
    And I click on the "delete token" icon for that token
    And I cancel the deletion in the confirmation dialog
    Then the deletion action is cancelled