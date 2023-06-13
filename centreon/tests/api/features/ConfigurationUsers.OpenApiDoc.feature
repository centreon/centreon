Feature:
  In order to get information on the current user
  As a user
  I want retrieve those information

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Get users parameters
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;kev;kev;kev@localhost;Centreon@2022;1;1;en_US;local
    """

    When I send a GET request to '/api/v22.04/configuration/users?search={"alias":"kev"}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 20,
                "alias": "kev",
                "name": "kev",
                "email": "kev@localhost",
                "is_admin": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
                "$and": {
                    "alias": "kev"
                }
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Get and edit current user parameters
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;setparam;admin;timezone;Europe/Paris
    """
    And the configuration is generated and exported

    When I send a GET request to '/api/latest/configuration/users/current/parameters'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
      "id": 1,
      "name": "admin admin",
      "alias": "admin",
      "email": "admin@centreon.com",
      "timezone": "Europe/Paris",
      "locale": "en_US",
      "is_admin": true,
      "use_deprecated_pages": false,
      "is_export_button_enabled": true,
      "theme": "light",
      "user_interface_density": "compact",
      "default_page": null
    }
    """

    When I send a PATCH request to '/api/latest/configuration/users/current/parameters' with body:
    """
    { "theme": "dark" }
    """
    Then the response code should be "204"

    When I send a PATCH request to '/api/latest/configuration/users/current/parameters' with body:
    """
    { "user_interface_density": "extended" }
    """
    Then the response code should be "204"

