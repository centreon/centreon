Feature:
  In order to check the time period configuration
  As a logged in user
  I want to check all the API endpoints of time periods

  Background:
    Given a running instance of Centreon Web API

  Scenario: Time period listing
    Given I am logged in
    When I send a GET request to '/api/latest/configuration/timeperiods'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "result": [
          {
              "id": 1,
              "name": "24x7",
              "alias": "Always",
              "days": [
                  {
                      "day": 1,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 2,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 3,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 4,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 5,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 6,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 7,
                      "time_range": "00:00-24:00"
                  }
              ],
              "templates": [],
              "exceptions": []
          },
          {
              "id": 2,
              "name": "none",
              "alias": "Never",
              "days": [],
              "templates": [],
              "exceptions": []
          },
          {
              "id": 3,
              "name": "nonworkhours",
              "alias": "Non-Work Hours",
              "days": [
                  {
                      "day": 1,
                      "time_range": "00:00-09:00,17:00-24:00"
                  },
                  {
                      "day": 2,
                      "time_range": "00:00-09:00,17:00-24:00"
                  },
                  {
                      "day": 3,
                      "time_range": "00:00-09:00,17:00-24:00"
                  },
                  {
                      "day": 4,
                      "time_range": "00:00-09:00,17:00-24:00"
                  },
                  {
                      "day": 5,
                      "time_range": "00:00-09:00,17:00-24:00"
                  },
                  {
                      "day": 6,
                      "time_range": "00:00-24:00"
                  },
                  {
                      "day": 7,
                      "time_range": "00:00-24:00"
                  }
              ],
              "templates": [],
              "exceptions": []
          },
          {
              "id": 4,
              "name": "workhours",
              "alias": "Work hours",
              "days": [
                  {
                      "day": 1,
                      "time_range": "09:00-17:00"
                  },
                  {
                      "day": 2,
                      "time_range": "09:00-17:00"
                  },
                  {
                      "day": 3,
                      "time_range": "09:00-17:00"
                  },
                  {
                      "day": 4,
                      "time_range": "09:00-17:00"
                  },
                  {
                      "day": 5,
                      "time_range": "09:00-17:00"
                  }
              ],
              "templates": [],
              "exceptions": []
          }
      ],
      "meta": {
          "page": 1,
          "limit": 10,
          "search": {},
          "sort_by": {},
          "total": 4
      }
    }
    """