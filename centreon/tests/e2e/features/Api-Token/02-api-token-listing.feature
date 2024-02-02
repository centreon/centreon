@ignore
@REQ_MON-21273
Feature: List Existing API Tokens in Administration

  As an administrator
  I want to view all existing API tokens under "Administration > App token"
  So that I can manage and oversee token details

  Background:
    Given I am logged in as an administrator
    And I am on the "Administration" section

  Scenario Outline: View Existing API Tokens
    When I navigate to "App token" under "Administration"
    Then a list of API tokens is displayed with the following fields
      | Status          | Name          | Creator        | User          | Creation date   | Expiration date |
      | <Status>        | <Name>        | <Creator>      | <User>        | <CreationDate>  | <ExpirationDate>|
    Examples:
      | Status      | Name       | Creator  | User   | Creation date | Expiration date |
      | Active      | Token_1    | Admin    | User_1 | 2023-10-15    | 2024-10-15      |
      | Active      | Token_2    | Admin    | User_2 | 2023-09-20    | 2024-09-20      |
      | Active      | Token_3    | Admin    | User_3 | 2023-08-10    | 2024-08-10      |
      | Active      | Token_4    | Admin    | User_4 | 2023-07-05    | 2024-07-05      |
      | Active      | Token_5    | Admin    | User_5 | 2023-06-01    | 2024-06-01      |