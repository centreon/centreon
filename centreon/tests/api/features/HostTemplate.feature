Feature:
  In order to check the host templates
  As a logged in user
  I want to find host templates using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host templates creation listing
    Given I am logged in
    And the following CLAPI import data:
    """
    HTPL;ADD;htpl-name-1;htpl-alias-1;;;;
    """

    When I send a GET request to '/api/latest/configuration/hosts/templates?search={"name":{"$lk":"htpl-%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [
        {
          "id": 15,
          "name": "htpl-name-1",
          "alias": "htpl-alias-1",
          "snmp_version": null,
          "snmp_community": null,
          "timezone_id": null,
          "severity_id": null,
          "check_command_id": null,
          "check_command_args": null,
          "check_timeperiod_id": null,
          "max_check_attempts": null,
          "normal_check_interval": null,
          "retry_check_interval": null,
          "active_check_enabled": 2,
          "passive_check_enabled": 2,
          "notification_enabled": 2,
          "notification_options": 31,
          "notification_interval": null,
          "notification_timeperiod_id": null,
          "add_inherited_contact_group": false,
          "add_inherited_contact": false,
          "first_notification_delay": null,
          "recovery_notification_delay": null,
          "acknowledgement_timeout": null,
          "freshness_checked": 2,
          "freshness_threshold": null,
          "flap_detection_enabled": 2,
          "low_flap_threshold": null,
          "high_flap_threshold": null,
          "event_handler_enabled": 2,
          "event_handler_command_id": null,
          "event_handler_command_args": null,
          "note_url": null,
          "note": null,
          "action_url": null,
          "icon_id": null,
          "icon_alternative": null,
          "comment": null,
          "is_activated": true,
          "is_locked": false
        }
      ],
      "meta": {
        "page": 1,
        "limit": 10,
        "search": {
          "$and": {
            "name": {
              "$lk": "htpl-%"
            }
          }
        },
        "sort_by": {},
        "total": 1
      }
    }
    """

  Scenario: Host template deletion
    Given I am logged in
    And the following CLAPI import data:
      """
      HTPL;ADD;htpl-name-1;htpl-alias-1;;;;
      """

    When I send a GET request to '/api/latest/configuration/hosts/templates?search={"name":{"$lk":"htpl-%"}}'
    Then the response code should be "200"
    And I store response values in:
      | name           | path         |
      | hostTemplateId | result[0].id |

    When I send a DELETE request to '/api/latest/configuration/hosts/templates/<hostTemplateId>'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/hosts/templates?search={"name":{"$lk":"htpl-%"}}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Host template creation
    Given I am logged in

    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "  host template name  ",
        "alias": "  host-template-alias  ",
        "snmp_version": "2c",
        "snmp_community": "   snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_command_id": 1,
        "check_command_args": "checkCommandArgs-value",
        "check_timeperiod_id": 1,
        "max_check_attempts": 5,
        "normal_check_interval": 5,
        "retry_check_interval": 5,
        "is_active_check_enabled": 1,
        "is_passive_check_enabled": 1,
        "is_notification_enabled": 2,
        "notification_options": null,
        "notification_interval": 5,
        "notification_timeperiod_id": 2,
        "add_inherited_contact_group": true,
        "add_inherited_contact": true,
        "first_notification_delay": 5,
        "recovery_notification_delay": 5,
        "acknowledgement_timeout": 5,
        "is_freshness_checked": 2,
        "freshness_threshold": 5,
        "is_flap_detection_enabled": 2,
        "low_flap_threshold": 5,
        "high_flap_threshold": 5,
        "is_event_handler_enabled": 2,
        "event_handler_command_id": 2,
        "event_handler_command_args": "eventHandlerCommandArgs\nvalue",
        "note_url": 'noteUrl-value',
        "note": 'note-value',
        "action_url": 'actionUrl-value',
        "icon_id": 1,
        "icon_alternative": 'iconAlternative-value',
        "comment": 'comment-value',
        "is_activated": false
      }
      """
    Then the response code should be "201"
    And the JSON should be equal to:
      """
      {
        "id": 15,
        "name": "host_template_name",
        "alias": "host-template-alias",
        "snmp_version": "2c",
        "snmp_community": "snmpCommunity-value",
        "timezone_id": 1,
        "severity_id": 1,
        "check_command_id": 1,
        "check_command_args": "checkCommandArgs-value",
        "check_timeperiod_id": 1,
        "max_check_attempts": 5,
        "normal_check_interval": 5,
        "retry_check_interval": 5,
        "is_active_check_enabled": 1,
        "is_passive_check_enabled": 1,
        "is_notification_enabled": 2,
        "notification_options": 31,
        "notification_interval": 5,
        "notification_timeperiod_id": 2,
        "add_inherited_contact_group": true,
        "add_inherited_contact": true,
        "first_notification_delay": 5,
        "recovery_notification_delay": 5,
        "acknowledgement_timeout": 5,
        "is_freshness_checked": 2,
        "freshness_threshold": 5,
        "is_flap_detection_enabled": 2,
        "low_flap_threshold": 5,
        "high_flap_threshold": 5,
        "is_event_handler_enabled": 2,
        "event_handler_command_id": 2,
        "event_handler_command_args": "eventHandlerCommandArgs#BR#value",
        "note_url": 'noteUrl-value',
        "note": 'note-value',
        "action_url": 'actionUrl-value',
        "icon_id": 1,
        "icon_alternative": 'iconAlternative-value',
        "comment": 'comment-value',
        "is_activated": false,
        "is_locked": false
      }
      """

    # conflict on name
    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      {
        "name": "host_template name",
        "alias": "host-template-alias",
        "is_activated": true
      }
      """
    Then the response code should be "409"

    # missing mandatory fields
    When I send a POST request to '/api/latest/configuration/hosts/templates' with body:
      """
      { "not_exists": "foo-bar" }
      """
    Then the response code should be "400"
    And the JSON should be equal to:
      """
      {
        "code": 400,
        "message": "[name] The property name is required\n[alias] The property alias is required\nThe property not_exists is not defined and the definition does not allow additional properties\n"
      }
      """
