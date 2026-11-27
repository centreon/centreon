Feature: In-application error messages
  As a Centreon user
  I want an unknown page or a failed API call to be reported inside the application
  So that I can keep navigating instead of facing a blank page

  @MON-214344
  Scenario: An unknown page or a failed API call shows a message inside the application, never a blank page
    Given an admin user is logged in a Centreon server
    When the user opens an unknown page of the application
    Then the page "404" with "This page could not be found" is displayed with the main menu
    When the user opens an unknown legacy page
    Then the message "You are not allowed to reach this page" is displayed inside the application
    When the user opens the edit form of a host group that does not exist
    Then the error message "Host group not found" is displayed and the listing stays displayed
    When the user clicks "Resources Status" in the main menu
    Then the Resources Status page is displayed
