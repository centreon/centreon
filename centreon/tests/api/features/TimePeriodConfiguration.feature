Feature:
  In order to check the time period configuration
  As a logged in user
  I want to check all the API endpoints of time periods

  Background:
    Given a running instance of Centreon Web API

  Scenario: Time period listing
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/timeperiods' with body:
    """
    {
        "name": "test_name",
        "alias": "test_alias",
        "days": [
            {
                "day": 1,
                "time_range": "06:30-07:00"
            },
            {
                "day": 7,
                "time_range": "06:30-07:00,09:00-10:30"
            }
        ],
        "templates": [
            1
        ],
        "exceptions": [
            {
                "day_range": "monday 1",
                "time_range": "06:00-07:00"
            }
        ]
    }
    """
    When I send a GET request to '/api/latest/configuration/timeperiods?search={"name": "test_name"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 5,
                "name": "test_name",
                "alias": "test_alias",
                "days": [
                    {
                        "day": 1,
                        "time_range": "06:30-07:00"
                    },
                    {
                        "day": 7,
                        "time_range": "06:30-07:00,09:00-10:30"
                    }
                ],
                "templates": [
                    {
                        "id": 1,
                        "alias": "Always"
                    }
                ],
                "exceptions": [
                    {
                        "id": 1,
                        "day_range": "monday 1",
                        "time_range": "06:00-07:00"
                    }
                ]
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {},
            "sort_by": {},
            "total": 7
        }
    }
    """
    When I send a POST request to '/api/latest/configuration/timeperiods' with body:
    """
    {
        "name": "test_name",
        "alias": "test_alias",
        "days": [
            {
                "day": 1,
                "time_range": "06:30-07:00"
            },
            {
                "day": 7,
                "time_range": "06:30-07:00,09:00-10:30"
            }
        ],
        "templates": [
            1
        ],
        "exceptions": [
            {
                "day_range": "monday 1",
                "time_range": "06:00-07:00"
            }
        ]
    }
    """
    Then the response code should be 500