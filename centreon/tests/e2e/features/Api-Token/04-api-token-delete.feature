@ignore
@REQ_MON-21278
Feature: Delete API Token

  As an administrator
  I want to delete an API token using the "delete token" icon
  So that I can manage the tokens effectively

  Background:
    Given I am logged in as an administrator
    And I am on the API tokens page

  Scenario: Delete API Token with Confirmation
    When I locate the API token to delete
    And I click on the "delete token" icon for that token
    And I confirm the deletion in the confirmation dialog
    Then the token is deleted successfully