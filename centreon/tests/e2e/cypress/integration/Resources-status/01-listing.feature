<<<<<<< HEAD
Feature: List Resources
  As a user
  I want to list the available Resources and filter them
  So that I can handle associated problems quickly and efficiently 

  Scenario: Accessing the page for the first time
    Then the unhandled problems filter is selected
    And only non-ok resources are displayed

  Scenario: Filtering Resources through criterias
    When I put in some criterias 
    Then only the Resources matching the selected criterias are displayed in the result

  Scenario: Selecting filters
=======
Feature: Filter a list of Resources
  As a user
  I want to apply filter(s) on a list of Resources
  So that I can quickly view a specific group of these Resources

  Scenario: I first access to the page
    When I filter on unhandled problems
    Then Only non-ok resources are displayed

  Scenario: I can filter Resources
    When I put in some criterias 
    Then only the Resources matching the selected criterias are displayed in the result

  Scenario: I can select filters
>>>>>>> centreon/dev-21.10.x
    Given a saved custom filter
    When I select the custom filter
    Then only Resources matching the selected filter are displayed in the result
