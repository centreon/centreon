Feature: Custom views
  As a Centreon user
  I want to share my custom views
  So that other users can benefit from it

  Background:
    Given an admin user is logged in a Centreon server

  @TEST_MON-162026
  Scenario: Share public custom view
    Given a publicly shared custom view is configured
    Given a user with custom views edition rights on the custom views listing page
    When the user wishes to add a new custom view
    Then he can add the public view
    And he cannot modify the content of the shared view

  @TEST_MON-162028
  Scenario: Remove public share
    Given a publicly shared custom view is configured by the owner
    Given a user with custom views edition rights on the custom views listing page
    Given the user is using the public view
    When he removes the shared view
    Then the view is not visible anymore
    And the user can use the public view again

  @TEST_MON-162029
  Scenario: Remove public share by owner
    Given a user with custom views edition rights on the custom views listing page
    Given the user is using the public view
    When the owner removes the view
    Then the view is not visible anymore for the user