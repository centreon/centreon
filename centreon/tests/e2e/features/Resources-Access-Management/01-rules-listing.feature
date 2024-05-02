@REQ_MON-59090
Feature: Pagination and Search Functionality in Resource Access Management

  Background:
    Given I am logged in as a user with administrator role
    And I have access to the Administration > ACL > Resource Access Management feature

  Scenario: Viewing resource access rules
    When I navigate to the Resource Access Management page
    Then I should see a table with columns: "Name", "Description", "Actions", "Status"
    And a button to add a new rule is available

  Scenario: Pagination functionality
    When I navigate to the Resource Access Management page
    Then I should see at least 10 rules registered
    And the default pagination should be set to 10 per page
    When I click on the next page button
    Then I should see the next 5 rules displayed
    When I click on the previous page button
    Then I should see the previous first 10 rules displayed

  Scenario: Search functionality
    When I navigate to the Resource Access Management page
    And I enter a search query in the search field for a rule or description
    Then I should see only the rules that match the search query
