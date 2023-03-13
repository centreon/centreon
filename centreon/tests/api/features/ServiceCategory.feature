Feature:
  In order to check the service categories
  As a logged in user
  I want to find service categories using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Service categories listing as admin
    Given I am logged in
    And the following CLAPI import data:
    """
    SC;ADD;service-cat1;service-cat1-alias
    """

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 5,
                "name": "service-cat1",
                "alias": "service-cat1-alias",
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
              "$and": {"name": {"$lk": "service-cat%"}}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Service categories listing as non-admin with ACL filter
    Given the following CLAPI import data:
    """
    SC;ADD;service-cat1;service-cat1-alias
    SC;ADD;service-cat2;service-cat2-alias
    CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Services;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;addfilter_servicecategory;ACL Resource test;service-cat2
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 6,
                "name": "service-cat2",
                "alias": "service-cat2-alias",
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
              "$and": {"name": {"$lk": "service-cat%"}}
            },
            "sort_by": {},
            "total": 1
        }
    }
    """

  Scenario: Service categories listing as non-admin without ACL filter
    Given the following CLAPI import data:
    """
    SC;ADD;service-cat1;service-cat1-alias
    SC;ADD;service-cat2;service-cat2-alias
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

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 5,
                "name": "service-cat1",
                "alias": "service-cat1-alias",
                "is_activated": true
            },
            {
                "id": 6,
                "name": "service-cat2",
                "alias": "service-cat2-alias",
                "is_activated": true
            }
        ],
        "meta": {
            "page": 1,
            "limit": 10,
            "search": {
              "$and": {"name": {"$lk": "service-cat%"}}
            },
            "sort_by": {},
            "total": 2
        }
    }
    """

  Scenario: Delete a service category as admin user
    Given I am logged in
    And the following CLAPI import data:
        """
        SC;ADD;service-cat1;service-cat1-alias
        """

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/categories/5'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    Given the following CLAPI import data:
        """
        SC;ADD;service-sev1;service-sev1-alias
        SC;setseverity;service-sev1;1;logos/logo-centreon-colors.png
        """

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

    When I send a GET request to '/api/latest/configuration/services/severities'
    Then the response code should be "200"
    And I store response values in:
      | name       | path         |
      | severityId | result[0].id |

    When I send a DELETE request to '/api/latest/configuration/services/categories/<severityId>'
    Then the response code should be "404"

  Scenario: Delete a service category as non-admin user with ACL filter
    Given the following CLAPI import data:
        """
        SC;ADD;service-cat1;service-cat1-alias
        CONTACT;ADD;ala;ala;ala@localservice.com;Centreon@2022;0;1;en_US;local
        CONTACT;setparam;ala;reach_api;1
        ACLMENU;add;ACL Menu test;my alias
        ACLMENU;grantrw;ACL Menu test;1;Configuration;Services;Categories
        ACLRESOURCE;add;ACL Resource test;my alias
        ACLRESOURCE;addfilter_servicecategory;ACL Resource test;service-cat1
        ACLGROUP;add;ACL Group test;my alias
        ACLGROUP;addmenu;ACL Group test;ACL Menu test
        ACLGROUP;addresource;ACL Group test;ACL Resource test
        ACLGROUP;addcontact;ACL Group test;ala
        """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/categories/5'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Delete a service category as non-admin user without ACL filter
    Given the following CLAPI import data:
        """
        SC;ADD;service-cat1;service-cat1-alias
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

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/services/categories/5'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/services/categories?search={"name":{"$lk":"service-cat%"}}'
    Then the response code should be "200"
    And the json node "result" should have 0 elements

  Scenario: Create a service category
    Given I am logged in

    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "name": "   service-cat-name   ",
        "alias": "   service-cat-alias   "
        }
        """
    Then the response code should be "201"
    And the JSON should be equal to:
        """
        {
        "id": 5,
        "name": "service-cat-name",
        "alias": "service-cat-alias",
        "is_activated": true
        }
        """
    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "name": "service-cat-name",
        "alias": "service-cat-alias"
        }
        """
    Then the response code should be "409"

  Scenario: Create service category with an invalid payload
    Given I am logged in
    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "not_existing": "Hello World"
        }
        """
    Then the response code should be "400"

    When I send a POST request to '/api/latest/configuration/services/categories' with body:
        """
        {
        "name": "",
        "alias": "service-cat-alias"
        }
        """
    Then the response code should be "400"
