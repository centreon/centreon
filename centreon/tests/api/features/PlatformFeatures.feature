@system
Feature:
    In order to maintain easily centreon platform
    As a user
    I want to gather informations about feature on centreon web using api

    Scenario: Platform features format
        Given a running instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation
        And I am logged in

        And I send a GET request to '/api/latest/platform/features'
        Then the response code should be "200"
        And the JSON node "is_cloud_platform" should be equal to "false"
        And the JSON node "feature_flags" should exist

    Scenario: Platform features with cloud platform flag TRUE
        Given a running cloud platform instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation
        And I am logged in

        And I send a GET request to '/api/latest/platform/features'
        Then the response code should be "200"
        And the JSON node "is_cloud_platform" should be equal to "true"

    Scenario: Platform features with feature flag ON
        Given a running instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation
        And a feature flag "fake_flag_for_platform_features" of bitmask 3
        And I am logged in

        And I send a GET request to '/api/latest/platform/features'
        Then the response code should be "200"
        And the JSON node "feature_flags.fake_flag_for_platform_features" should be equal to "true"

    Scenario: Platform features with feature flag OFF
        Given a running instance of Centreon Web API
        And the endpoints are described in Centreon Web API documentation
        And a feature flag "fake_flag_for_platform_features" of bitmask 0
        And I am logged in

        And I send a GET request to '/api/latest/platform/features'
        Then the response code should be "200"
        And the JSON node "feature_flags.fake_flag_for_platform_features" should be equal to "false"
