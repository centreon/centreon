@REQ_MON-22206
Feature: Add a downtime on a resource
  As a user
  I would like to be able to add a downtime on a resource
  So that the users of the platform do not receive any more notifications about this resource during downtime

  Background:
    Given the user have the necessary rights to page Resource Status
    And the user have the necessary rights to set downtime
    And minimally one resource with notifications enabled on user

  @TEST_MON-22207
  Scenario: Set a downtime on resource with default settings
    Given a resource is selected
    When the user click on the "Set downtime" action
    And the user fill in the required fields on the start date now, and validate it
    Then the user must be notified of the sending of the order
    And I see the resource as downtime in the listing

  @TEST_MON-22209
  Scenario: Set a downtime more one resource with default settings
    Given multiple resources are selected
    When the user click on the "Set downtime" action
    And the user fill in the required fields on the start date now, and validate it
    Then the user must be notified of the sending of the order
    And the user should see the downtime resources appear in the listing after a refresh

  @TEST_MON-22208
  Scenario: Cancel a downtime on a resource
    Given a resource is in downtime
    And that you have to go to the downtime page
    When I search for the resource currently "In Downtime" in the list
    Then the user starts downtime configuration on the resource
    And the user cancels the downtime configuration
    Then the line disappears from the listing
    Then the user goes to the Resource Status page
    And the resource should not be in Downtime anymore

  @TEST_MON-22210
  Scenario: Cancel multiple downtimes on multiple resources
    Given multiple resources are in downtime
    Given that you have to go to the downtime page
    When I search for the resources currently "In Downtime" in the list
    Then the user starts downtime configuration on the resources
    And the user cancels the downtime configuration
    Then the lines disappears from the listing
    Then the user goes to the Resource Status page
    And the resources should not be in Downtime anymore
