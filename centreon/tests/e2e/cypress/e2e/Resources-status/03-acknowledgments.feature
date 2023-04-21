Feature: Add an acknowledgement on a resource with a problem
    As a user
    I would like to be able to add an acknowledgement on a problematic resource
    So that the users of the platform do not receive any more notifications about the problem until acknowledgement is terminated.

Background:
    Given the user have the necessary rights to page Ressource Status
    And the user have the necessary rights to acknowledge & disacknowledge
    And there are at least two resources of each type with a problem and notifications enabled for the user

Scenario: Acknowledge a single resource configured with default settings
    Given a single resource selected on Resources Status with the "Resource Problems" filter enabled
    And acknowledgment column is enabled in Resource Status
    When the user uses one of the "Acknowledge" actions
    And the user fills in the required fields in the form with default parameters "sticky & persistent checked"
    And the user applies the acknowledgement
    Then the user is notified by the UI about the acknowledgement command being sent
    And the previously selected resource is marked as acknowledged in the listing with the corresponding colour
    And the previously selected resource is marked as acknowledged in the listing with the acknowledgement icon
    And the tooltip on acknowledgement icon contains the information related to the acknowledgment