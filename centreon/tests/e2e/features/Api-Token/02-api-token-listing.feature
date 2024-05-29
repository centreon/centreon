@REQ_MON-21273
Feature: List Existing API Tokens in Administration

  As an administrator
  I want to view all existing API tokens under "Administration > App token"
  So that I can manage and oversee token details

  # Background:
    # Given I am logged in as an administrator

  @TEST_MON-36699
  Scenario: View existing API Tokens
    Given I am logged in as an administrator
    # Given API tokens with the following details are created
    #   | Name    | User   | Duration   |
    #   | <Name>  | <User> | <Duration> |
    # When I navigate to API tokens page
    # Then a list of API tokens is displayed with the following fields
    #   | Name   | User   | Creator     |
    #   | <Name> | <User> | admin admin |
    # And the Creation Date field has the current day as value
    # And the Expiration Date field has the current day plus "<Duration>" as value
    # Examples:
    #   | Name    | User   | Duration |
    #   | Token_1 | User_1 | 7 days   |
    #   | Token_2 | User_2 | 30 days  |
    #   | Token_3 | User_3 | 60 days  |
    #   | Token_4 | User_4 | 90 days  |
    #   | Token_5 | User_5 | 1 year   |
