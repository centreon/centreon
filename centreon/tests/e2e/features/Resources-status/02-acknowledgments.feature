@REQ_MON-22199
Feature: Add an acknowledgement on a resource with a problem
  As a user
  I would like to be able to add an acknowledgement on a problematic resource
  So that the users of the platform do not receive any more notifications about the problem until acknowledgement is terminated.

  Background:
    Given the user has the necessary rights to page Resource Status
    And the user has the necessary rights to acknowledge & disacknowledge
    And there are at least two resources of each type with a problem and notifications enabled for the user

  @TEST_MON-22202
  Scenario: Acknowledge a single resource configured with default settings
    Given a single resource selected on Resources Status with the "Resource Problems" filter enabled
    And acknowledgment column is enabled in Resource Status
    When the user uses one of the "Acknowledge" actions
    And the user fills in the required fields in the form with default parameters "sticky checked"
    And the user applies the acknowledgement
    Then the user is notified by the UI about the acknowledgement command being sent
    And the previously selected resource is marked as acknowledged in the listing with the corresponding color
    And the previously selected resource is marked as acknowledged in the listing with the acknowledgement icon
    And the tooltip on acknowledgement icon contains the information related to the acknowledgment

  # @TEST_MON-22203
  # Scenario: Acknowledge multiple resources with default settings
  #   Given multiple resources selected on Resources Status with the "Resource Problems" filter enabled
  #   And acknowledgment column is enabled in Resource Status
  #   When the user uses one of the "Acknowledge" actions
  #   And the user fills in the required fields in the form with default parameters "sticky checked"
  #   And the user applies the acknowledgement
  #   Then the user is notified by the UI about the acknowledgement command being sent
  #   And the previously selected resources are marked as acknowledged in the listing with the corresponding color
  #   And the previously selected resources is marked as acknowledged in the listing with the acknowledgement icon
  #   And the tooltip on acknowledgement icon for each resource contains the information related to the acknowledgment

  # @TEST_MON-22204
  # Scenario Outline: Acknowledge a resource with sticky only on a host
  #   Given the "Resource Problems" filter enabled
  #   And criteria is 'type:host'
  #   And a resource of host is selected with '<initial_status>'
  #   When the user uses one of the "Acknowledge" actions
  #   And "sticky" checkbox is 'checked' in the form
  #   And the user applies the acknowledgement
  #   And the 'host' resource is marked as acknowledged
  #   When the 'host' status changes to '<changed_status>'
  #   Then no notification are sent to the users

  #   Examples:
  #     | initial_status | changed_status |
  #     | down           | unreachable    |
  #     | unreachable    | up             |

  # @TEST_MON-22201
  # Scenario Outline: Acknowledge a resource with sticky only on a service
  #   Given the "Resource Problems" filter enabled
  #   And criteria is 'type:service'
  #   And a resource of service is selected with '<initial_status>'
  #   And the user uses one of the "Acknowledge" actions
  #   And "sticky" checkbox is 'checked' in the form
  #   And the user applies the acknowledgement
  #   And the 'service' resource is marked as acknowledged
  #   When the 'service' status changes to '<changed_status>'
  #   Then no notification are sent to the users

  #   Examples:
  #     | initial_status | changed_status |
  #     | warning        | critical       |
  #     | critical       | unknown        |
  #     | unknown        | warning        |

  # @TEST_MON-22200
  # Scenario: Disacknowledge a resource
  #   Given a single resource selected on Resources Status with the criteria "state: acknowledged"
  #   And a resource marked as acknowledged is selected
  #   And the user uses the "Disacknowledge" action for this resource in the "More actions" menu
  #   Then the acknowledgement is removed
  #   Then the resource is not marked as acknowledged after listing is refreshed with the criteria "state: acknowledged"
