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
                "name": "kev",
                "alias": "kev",
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
      "can_manage_api_tokens": true,
      "theme": "light",
      "user_interface_density": "compact",
      "default_page": null,
      "dashboard": {
          "global_user_role": "administrator",
          "view_dashboards": true,
          "create_dashboards": true,
          "administrate_dashboards": true
      }
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

    When I send a GET request to '/api/latest/configuration/users/current/parameters'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | theme                  | "dark"     |
      | user_interface_density | "extended" |

  Scenario: Check for undefined dashboard permissions with the CREATOR role + the dashboard feature flag OFF
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    ACLMENU;add;name-creator-ACLMENU;alias-creator-ACLMENU
    ACLMENU;grantrw;name-creator-ACLMENU;0;Home;Dashboards;Creator;
    ACLGROUP;add;name-creator-ACLGROUP;alias-creator-ACLGROUP
    ACLGROUP;addmenu;name-creator-ACLGROUP;name-creator-ACLMENU
    ACLGROUP;setcontact;name-creator-ACLGROUP;usr-creator;
    """
    Given I am logged in with "usr-creator"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 0

    When I send a GET request to '/api/latest/configuration/users/current/parameters'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name                              | "usr-creator"  |
    And the JSON node "dashboard" should not exist

  Scenario: Check for nullable dashboard permissions with the NO role + the dashboard feature flag ON
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    """
    Given I am logged in with "usr-creator"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 3

    When I send a GET request to '/api/latest/configuration/users/current/parameters'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name                              | "usr-creator"  |
      | dashboard                         | null           |

  Scenario: Check for presence of dashboard permissions with the CREATOR role + the dashboard feature flag ON
    Given I am logged in
    And the following CLAPI import data:
    """
    CONTACT;ADD;usr-creator;usr-creator;usr-creator@centreon.test;Centreon@2023;0;1;en_US;local
    CONTACT;setparam;usr-creator;reach_api;1
    ACLMENU;add;name-creator-ACLMENU;alias-creator-ACLMENU
    ACLMENU;grantrw;name-creator-ACLMENU;0;Home;Dashboards;Creator;
    ACLGROUP;add;name-creator-ACLGROUP;alias-creator-ACLGROUP
    ACLGROUP;addmenu;name-creator-ACLGROUP;name-creator-ACLMENU
    ACLGROUP;setcontact;name-creator-ACLGROUP;usr-creator;
    """
    Given I am logged in with "usr-creator"/"Centreon@2023"
    And a feature flag "dashboard" of bitmask 3

    When I send a GET request to '/api/latest/configuration/users/current/parameters'
    Then the response code should be "200"
    And the JSON nodes should be equal to:
      | name                              | "usr-creator"  |
      | dashboard.global_user_role        | "creator"      |
      | dashboard.view_dashboards         | true           |
      | dashboard.create_dashboards       | true           |
      | dashboard.administrate_dashboards | false          |
