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
        "not_existing": "Hello World"
    }
    """
    Then the response code should be "400"

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
    Then the response code should be "201"
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
            "search": {
                "$and": {
                    "name": "test_name"
                }
            },
            "sort_by": {},
            "total": 1
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
    Then the response code should be 409
    And the JSON should be equal to:
    """
    {
        "code": 409,
        "message": "The time period name 'test_name' already exists"
    }
    """

    When I send a PUT request to '/api/latest/configuration/timeperiods/5' with body:
    """
    {
        "not_existing": "Hello World"
    }
    """
    Then the response code should be "400"

    When I send a PUT request to '/api/latest/configuration/timeperiods/5' with body:
    """
    {
        "name": "test_name2",
        "alias": "test_alias2",
        "days": [
            {
                "day": 1,
                "time_range": "06:30-07:01"
            },
            {
                "day": 7,
                "time_range": "06:30-07:00,09:00-10:31"
            }
        ],
        "templates": [
            2
        ],
        "exceptions": [
            {
                "day_range": "monday 2",
                "time_range": "06:00-07:01"
            }
        ]
    }
    """
    Then the response code should be 204

    When I send a POST request to '/api/latest/configuration/timeperiods' with body:
    """
    {
        "name": "already_exists",
        "alias": "already_exists_alias",
        "days": [],
        "templates": [1],
        "exceptions": []
    }
    """
    Then the response code should be 201
    When I send a PUT request to '/api/latest/configuration/timeperiods/5' with body:
    """
    {
        "name": "already_exists",
        "alias": "already_exists_alias",
        "days": [],
        "templates": [1],
        "exceptions": []
    }
    """
    Then the response code should be 409
    And the JSON should be equal to:
    """
    {
        "code": 409,
        "message": "The time period name 'already_exists' already exists"
    }
    """

    When I send a GET request to '/api/latest/configuration/timeperiods?search={"name": "test_name2"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 5,
                "name": "test_name2",
                "alias": "test_alias2",
                "days": [
                    {
                        "day": 1,
                        "time_range": "06:30-07:01"
                    },
                    {
                        "day": 7,
                        "time_range": "06:30-07:00,09:00-10:31"
                    }
                ],
                "templates": [
                    {
                        "id": 2,
                        "alias": "Never"
                    }
                ],
                "exceptions": [
                    {
                        "id": 2,
                        "day_range": "monday 2",
                        "time_range": "06:00-07:01"
                    }
                ]
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "name": "test_name2"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """


    When I send a DELETE request to '/api/latest/configuration/timeperiods/5'
    Then the response code should be 204

    When I send a GET request to '/api/latest/configuration/timeperiods?search={"name": "test_name"}'
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
                    "name": "test_name"
                }
            },
            "sort_by": {},
            "total": 0
        }
    }
    """