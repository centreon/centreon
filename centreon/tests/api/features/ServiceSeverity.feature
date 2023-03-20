Feature:
  In order to check the service severities
  As a logged in user
  I want to find service severities using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service severities listing as admin
    Given I am logged in
    And the following CLAPI import data:
    """
    SC;ADD;severity1;service-severity-alias
    SC;setparam;severity1;sc_activate;1
    SC;setseverity;severity1;42;logos/logo-centreon-colors.svg
    """

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 5,
                "name": "severity1",
                "alias": "service-severity-alias",
                "level": 42,
                "icon_id": 1,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {},
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Service severities listing as non-admin with ACL filters
    Given the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/logo-centreon-colors.svg
    SC;ADD;service-sev2;service-sev2-alias
    SC;setseverity;service-sev2;2;logos/logo-centreon-colors.svg
    CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Services;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;addfilter_servicecategory;ACL Resource test;service-sev2
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 6,
                "name": "service-sev2",
                "alias": "service-sev2-alias",
                "level": 2,
                "icon_id": 1,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {},
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Service severities listing as non-admin without ACL filters
    Given the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/logo-centreon-colors.svg
    SC;ADD;service-sev2;service-sev2-alias
    SC;setseverity;service-sev2;2;logos/logo-centreon-colors.svg
    CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Services;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 5,
                "name": "service-sev1",
                "alias": "service-sev1-alias",
                "level": 1,
                "icon_id": 1,
                "is_activated": true
            },
            {
                "id": 6,
                "name": "service-sev2",
                "alias": "service-sev2-alias",
                "level": 2,
                "icon_id": 1,
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {},
            "sort_by": {},
            "total": 2
        }
    }
    """

  Scenario: Delete a service severity
    Given I am logged in
    And the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/logo-centreon-colors.svg
    """

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/severities/5'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    Given the following CLAPI import data:
    """
    SC;ADD;service-cat1;service-cat1-alias
    """

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And I store response values in:
      | name      | path              |
      | categoryId | result[0].id      |

    When I send a DELETE request to '/api/latest/configuration/services/severities/<categoryId>'
    Then the response code should be "404"

  Scenario: Delete a service severity as non-admin user with ACL filter
    Given the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/logo-centreon-colors.svg
    CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;1;Configuration;Services;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;addfilter_servicecategory;ACL Resource test;service-sev1
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/severities/5'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Delete a service severity as non-admin user without ACL filter
    Given the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/logo-centreon-colors.svg
    CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantrw;ACL Menu test;1;Configuration;Services;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/severities/5'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Create a service severity
    Given I am logged in

    When I send a POST request to '/api/latest/configuration/services/severities' with body:
    """
    {
        "name": "  service-sev-name  ",
        "alias": "  service-sev-alias  ",
        "level": 2,
        "icon_id": 1
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 5,
        "name": "service-sev-name",
        "alias": "service-sev-alias",
        "level": 2,
        "icon_id": 1,
        "is_activated": true
    }
    """

    # conflict on name
    When I send a POST request to '/api/latest/configuration/services/severities' with body:
    """
    {
        "name": "service-sev-name",
        "alias": "service-sev-alias",
        "level": 2,
        "icon_id": 1,
        "is_activated": true
    }
    """
    Then the response code should be "409"

    # conflict on name (should be trimmed by the repository
    When I send a POST request to '/api/latest/configuration/services/severities' with body:
    """
    {
        "name": "   service-sev-name   ",
        "alias": "service-sev-alias",
        "level": 2,
        "icon_id": 1,
        "is_activated": true
    }
    """
    Then the response code should be "409"

    # missing mandatory fields
    When I send a POST request to '/api/latest/configuration/services/severities' with body:
    """
    { "not_exists": "foo-bar" }
    """
    Then the response code should be "400"
    And the JSON should be equal to:
    """
    {
        "code": 400,
        "message": "[name] The property name is required\n[alias] The property alias is required\n[level] The property level is required\n[icon_id] The property icon_id is required\nThe property not_exists is not defined and the definition does not allow additional properties\n"
    }
    """
