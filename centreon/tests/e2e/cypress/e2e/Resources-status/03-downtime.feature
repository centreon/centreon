Feature: Add a downtime on a resource
    As a user
    I would like to be able to add a downtime on a resource
    So that the users of the platform do not receive any more notifications about this resource during downtime 

Background:
    Given the user have the necessary rights to page Resource Status
    And the user have the necessary rights to set downtime
    And minimally one resource with and notifications enabled on user

Scenario: Set a downtime on resource with default settings
    Given resource selected
    When the user click on the "Set downtime" action
    And the user fill in the required fields on the start date now, and validate it
    Then the user must be notified of the sending of the order
    And I see the resource as downtime in the listing

Scenario: Set a downtime more one resource with default settings
    Given multiple resources selected
    When the user click on the "Set downtime" action
    And the user fill in the required fields on the start date now, and validate it
    Then the user must be notified of the sending of the order
    And the user should see the downtime resources appear in the listing after a refresh

Scenario: Cancel a downtime on a resource
    Given a resource is on downtime
    And that you have to go to the downtime page
    When I search for the resource currently "In Downtime" in the list
    Then the user selects the checkbox and clicks on the "Cancel" action
    Then the user confirms the cancellation of the downtime
    Then the line disappears from the listing
    Then the user goes to the Resource Status page
    And the resource should not be in Downtime anymore

Scenario: Cancel multiple downtimes on multiple resources
    Given multiple resources are on downtime
    Given that you have to go to the downtime page
    When I search for the resources currently "In Downtime" in the list
    Then the user selects the checkboxes and clicks on the "Cancel" action
    Then the user confirms the cancellation of the downtime
    Then the lines disappears from the listing
    Then the user goes to the Resource Status page
    And the resources should not be in Downtime anymore