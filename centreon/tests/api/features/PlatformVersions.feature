Feature:
    In order to maintain easily centreon platform
    As a user
    I want to update centreon web using api

    Scenario: Platform versions format
        Given a running instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation
        And I am logged in

        And I send a GET request to '/api/latest/platform/versions'
        Then the response code should be "200"
        And the JSON node "web.version" should exist
        And the JSON node "web.major" should exist
        And the JSON node "web.minor" should exist
        And the JSON node "web.fix" should exist
        And the JSON node "modules" should exist
        And the JSON node "widgets" should exist
        And the JSON node "is_cloud_platform" should be equal to "false"

    Scenario: Platform versions with cloud platform flag TRUE
        Given a running cloud platform instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation
        And I am logged in

        And I send a GET request to '/api/latest/platform/versions'
        Then the response code should be "200"
        And the JSON node "is_cloud_platform" should be equal to "true"
