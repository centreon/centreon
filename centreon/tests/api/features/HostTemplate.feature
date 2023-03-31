Feature:
  In order to check the service categories
  As a logged in user
  I want to find service categories using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host templates listing
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
              "name": "htpl-alias-1",
              "alias": "htpl-alias-1",
              "snmpVersion": null,
              "snmpCommunity": null,
              "timezoneId": null,
              "severityId": null,
              "checkCommandId": null,
              "checkCommandArgs": null,
              "checkTimeperiodId": null,
              "maxCheckAttempts": null,
              "normalCheckInterval": null,
              "retryCheckInterval": null,
              "isActiveCheckEnabled": 2,
              "isPassiveCheckEnabled": 2,
              "isNotificationEnabled": 2,
              "notificationOptions": 31,
              "notificationInterval": null,
              "notificationTimeperiodId": null,
              "addInheritedContactGroup": false,
              "addInheritedContact": false,
              "firstNotificationDelay": null,
              "recoveryNotificationDelay": null,
              "acknowledgementTimeout": null,
              "isFreshnessChecked": 2,
              "freshnessThreshold": null,
              "isFlapDetectionEnabled": 2,
              "lowFlapThreshold": null,
              "highFlapThreshold": null,
              "isEventHandlerEnabled": 2,
              "eventHandlerCommandId": null,
              "eventHandlerCommandArgs": null,
              "noteUrl": null,
              "note": null,
              "actionUrl": null,
              "iconId": null,
              "iconAlternative": null,
              "comment": null,
              "isActivated": true,
              "isLocked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
              "$and": {"name": {"$lk": "htpl-%"}}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """
