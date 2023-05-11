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