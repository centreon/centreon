Feature:
  In order to check the service templates
  As a logged in user
  I want to find service templates using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service templates listing
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test;reach_api;1
    """
    
    When I send a POST request to '/api/latest/configuration/services/templates' with body:
    """
    {
        "name": "service template test",
        "alias": "service template alias",
        "comment": "comment",
        "service_template_id": 1,
        "check_command_id": 1,
        "check_command_args": ["arg1", "arg2"],
        "check_timeperiod_id": 1,
        "max_check_attempts": 5,
        "normal_check_interval": 3,
        "retry_check_interval": 2,
        "active_check_enabled": 2,
        "passive_check_enabled": 2,
        "volatility_enabled": 2,
        "notification_enabled": 2,
        "is_contact_additive_inheritance": false,
        "is_contact_group_additive_inheritance": false,
        "notification_interval": 6,
        "notification_timeperiod_id": 2,
        "notification_type": 36,
        "first_notification_delay": 19,
        "recovery_notification_delay": 9,
        "acknowledgement_timeout": 8,
        "freshness_checked": 2,
        "freshness_threshold": 11,
        "flap_detection_enabled": 2,
        "low_flap_threshold": 49,
        "high_flap_threshold": 51,
        "event_handler_enabled": 2,
        "event_handler_command_id": 2,
        "event_handler_command_args": ["earg1", "earg2"],
        "graph_template_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "icon_id": 1,
        "icon_alternative": "icon_alternative",
        "severity_id": null,
        "is_activated": true,
        "is_locked": false
    }
    """
    Then the response code should be 201
    And the JSON should be equal to:
    """
    {
        "id": 27,
        "name": "service template test",
        "alias": "service template alias",
        "comment": "comment",
        "service_template_id": 1,
        "check_command_id": 1,
        "check_command_args": [
            "arg1",
            "arg2"
        ],
        "check_timeperiod_id": 1,
        "max_check_attempts": 5,
        "normal_check_interval": 3,
        "retry_check_interval": 2,
        "active_check_enabled": 2,
        "passive_check_enabled": 2,
        "volatility_enabled": 2,
        "notification_enabled": 2,
        "is_contact_additive_inheritance": false,
        "is_contact_group_additive_inheritance": false,
        "notification_interval": 6,
        "notification_timeperiod_id": 2,
        "notification_type": 36,
        "first_notification_delay": 19,
        "recovery_notification_delay": 9,
        "acknowledgement_timeout": 8,
        "freshness_checked": 2,
        "freshness_threshold": 11,
        "flap_detection_enabled": 2,
        "low_flap_threshold": 49,
        "high_flap_threshold": 51,
        "event_handler_enabled": 2,
        "event_handler_command_id": 2,
        "event_handler_command_args": [
            "earg1",
            "earg2"
        ],
        "graph_template_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "icon_id": 1,
        "icon_alternative": "icon_alternative",
        "severity_id": null,
        "is_activated": true,
        "is_locked": false
    }
    """

    When I send a GET request to '/api/latest/configuration/services/templates?search={"name": "service template test"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 27,
                "name": "service template test",
                "alias": "service template alias",
                "comment": "comment",
                "service_template_id": 1,
                "check_command_id": 1,
                "check_command_args": [
                    "arg1",
                    "arg2"
                ],
                "check_timeperiod_id": 1,
                "max_check_attempts": 5,
                "normal_check_interval": 3,
                "retry_check_interval": 2,
                "active_check_enabled": 2,
                "passive_check_enabled": 2,
                "volatility_enabled": 2,
                "notification_enabled": 2,
                "is_contact_additive_inheritance": false,
                "is_contact_group_additive_inheritance": false,
                "notification_interval": 6,
                "notification_timeperiod_id": 2,
                "notification_type": 36,
                "first_notification_delay": 19,
                "recovery_notification_delay": 9,
                "acknowledgement_timeout": 8,
                "freshness_checked": 2,
                "freshness_threshold": 11,
                "flap_detection_enabled": 2,
                "low_flap_threshold": 49,
                "high_flap_threshold": 51,
                "event_handler_enabled": 2,
                "event_handler_command_id": 2,
                "event_handler_command_args": [
                    "earg1",
                    "earg2"
                ],
                "graph_template_id": 1,
                "note": "note",
                "note_url": "note_url",
                "action_url": "action url",
                "icon_id": 1,
                "icon_alternative": "icon_alternative",
                "severity_id": null,
                "is_activated": true,
                "is_locked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "service template test"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

    Given I am logged in with "test"/"Centreon@2022"
    When I send a DELETE request to '/api/latest/configuration/services/templates/27'
    Then the response code should be "403"

    When I am logged in
    Then I send a DELETE request to '/api/latest/configuration/services/templates/27'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/templates?search={"name": "service template test"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "service template test"
                }
            },
            "sort_by": {},
            "total": 0
        }
    }
    """
