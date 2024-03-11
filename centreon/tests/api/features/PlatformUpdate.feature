Feature:
    In order to maintain easily centreon platform
    As a user
    I want to update centreon web using api

    Background:
        Given a running instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation

    Scenario: Update platform information
        Given I am logged in

        When an update is available
        And I send a POST request to '/api/latest/platform/updates' with body:
        """
        """
        Then the response code should be "204"

        When I send a GET request to '/api/latest/platform/versions'
        Then the response code should be "200"
        And the JSON node "web.version" should be equal to the string "99.99.99"
