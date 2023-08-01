Feature:
  In order to check the service templates
  As a logged in user
  I want to find service templates using api

  Background:
    Given a running cloud platform instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service templates listing
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;test;test;test@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;test;reach_api;1
    SC;ADD;severity1;service-severity-alias
    SC;setparam;severity1;sc_activate;1
    SC;setseverity;severity1;42;logos/logo-centreon-colors.png
    """

    When I send a POST request to '/api/latest/configuration/services/templates' with body:
    """
      {
          "name": "templateA",
          "alias": "templateA",
          "service_template_id": 1,
          "macros": [
              {
                  "name": "MACRO1",
                  "value": "A1",
                  "is_password": false,
                  "description": null
              },
              {
                  "name": "MACROB",
                  "value": "B1",
                  "is_password": false,
                  "description": null
              },
              {
                  "name": "MACROC",
                  "value": "C1",
                  "is_password": false,
                  "description": null
              },
              {
                  "name": "MACROD",
                  "value": "D1",
                  "is_password": false,
                  "description": null
              },
              {
                  "name": "TOTO",
                  "value": "T1",
                  "is_password": false,
                  "description": null
              }
          ]
      }
    """
    Then the response code should be 201
    And I store response values in:
      | name      | path |
      | templateA | id   |

    When I send a POST request to '/api/latest/configuration/services/templates' with body:
    """
      {
          "name": "templateB",
          "alias": "templateB",
          "service_template_id": <templateA>,
          "macros": [
              {
                  "name": "MACROA",
                  "value": "A2",
                  "is_password": false,
                  "description": null
              },
              {
                  "name": "MACROC",
                  "value": "C2",
                  "is_password": false,
                  "description": null
              }
          ]
      }
    """
    Then the response code should be 201
    And I store response values in:
      | name      | path |
      | templateB | id   |

    When I send a POST request to '/api/latest/configuration/services/templates' with body:
    """
      {
          "name": "templateC",
          "alias": "templateC",
          "service_template_id": <templateB>,
          "macros": [
              {
                  "name": "MACROA",
                  "value": "A3",
                  "is_password": false,
                  "description": null
              },
              {
                  "name": "MACROB",
                  "value": "B2",
                  "is_password": false,
                  "description": null
              }
          ]
      }
    """
    Then the response code should be 201
    And I store response values in:
      | name      | path |
      | templateC | id   |

    When I send a POST request to '/api/latest/configuration/services/templates' with body:
    """
    {
        "name": "service template test",
        "alias": "service template alias",
        "service_template_id": <templateC>,
        "check_timeperiod_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "severity_id": null,
        "host_templates": [3, 11],
        "service_categories": [1, 2],
        "macros": [
            {
                "name": "MACROB",
                "value": "B3",
                "is_password": false,
                "description": null
            },
            {
                "name": "MACROE",
                "value": "E1",
                "is_password": false,
                "description": null
            },
            {
                "name": "TOTO",
                "value": "T1",
                "is_password": false,
                "description": null
            }
        ]
    }
    """
    Then the response code should be 201
    And the JSON should be equal to:
    """
    {
        "id": 30,
        "name": "service template test",
        "alias": "service template alias",
        "service_template_id": <templateC>,
        "check_timeperiod_id": 1,
        "note": "note",
        "note_url": "note_url",
        "action_url": "action url",
        "severity_id": null,
        "host_templates": [
            3,
            11
        ],
        "is_locked": false,
        "categories": [
            {
                "id": 1,
                "name": "Ping"
            },
            {
                "id": 2,
                "name": "Traffic"
            }
        ],
        "macros": [
            {
                "name": "MACROB",
                "value": "B3",
                "is_password": false,
                "description": ""
            },
            {
                "name": "MACROE",
                "value": "E1",
                "is_password": false,
                "description": ""
            }
        ]
    }
    """
    And I store response values in:
      | name      | path |
      | newServiceTemplateId | id   |

    When I send a GET request to '/api/latest/configuration/services/templates?search={"id": "<newServiceTemplateId>"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": <newServiceTemplateId>,
                "name": "service template test",
                "alias": "service template alias",
                "service_template_id": <templateC>,
                "check_timeperiod_id": 1,
                "note": "note",
                "note_url": "note_url",
                "action_url": "action url",
                "severity_id": null,
                "host_templates": [
                    3,
                    11
                ],
                "is_locked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "id": "<newServiceTemplateId>"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

    When I send a PATCH request to '/api/latest/configuration/services/templates/<newServiceTemplateId>' with body:
    """
    {
        "name": "new service template test",
        "alias": "new service template alias",
        "service_template_id": <templateB>,
        "check_timeperiod_id": 2,
        "note": "new note",
        "note_url": "new note_url",
        "action_url": "new action url",
        "severity_id": 5,
        "macros": [],
        "host_templates": [2, 3],
        "service_categories": [1, 3]
    }
    """
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/templates?search={"id": <newServiceTemplateId>}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": <newServiceTemplateId>,
                "name": "new service template test",
                "alias": "new service template alias",
                "service_template_id": <templateB>,
                "check_timeperiod_id": 2,
                "note": "new note",
                "note_url": "new note_url",
                "action_url": "new action url",
                "severity_id": 5,
                "host_templates": [
                    2,
                    3
                ],
                "is_locked": false
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "id": <newServiceTemplateId>
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

    Given I am logged in with "test"/"Centreon@2022"
    When I send a DELETE request to '/api/v23.10/configuration/services/templates/<newServiceTemplateId>'
    Then the response code should be "403"

    When I am logged in
    Then I send a DELETE request to '/api/v23.10/configuration/services/templates/<newServiceTemplateId>'
    Then the response code should be "204"

    When I send a GET request to '/api/v23.10/configuration/services/templates?search={"name": "service template test"}'
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
