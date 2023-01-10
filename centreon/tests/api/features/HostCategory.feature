Feature:
  In order to check the host categories
  As a logged in user
  I want to find host categories using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host categories listing as admin
    Given I am logged in
    And the following CLAPI import data:
    """
    HC;ADD;host-cat1;host-cat1-alias
    """

    When I send a GET request to '/api/latest/configuration/hosts/categories'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "host-cat1",
                "alias": "host-cat1-alias",
                "is_activated": true,
                "comments": null
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

  Scenario: Host categories listing as non-admin
    Given the following CLAPI import data:
    """
    HC;ADD;host-cat1;host-cat1-alias
    HC;ADD;host-cat2;host-cat2-alias
    CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Hosts;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-cat2
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/categories'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 2,
                "name": "host-cat2",
                "alias": "host-cat2-alias",
                "is_activated": true,
                "comments": null
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

  Scenario: Host categories listing as non-admin
    Given the following CLAPI import data:
    """
    HC;ADD;host-cat1;host-cat1-alias
    HC;ADD;host-cat2;host-cat2-alias
    CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Hosts;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-cat2
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/categories'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 2,
                "name": "host-cat2",
                "alias": "host-cat2-alias",
                "is_activated": true,
                "comments": null
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

  Scenario: Delete a host category
    Given I am logged in
    And the following CLAPI import data:
    """
    HC;ADD;host-cat1;host-cat1-alias
    """

    When I send a GET request to '/api/latest/configuration/hosts/categories'
    Then the response code should be "200"
    And the json node "result" should have 1 elements

    When I send a DELETE request to '/api/latest/configuration/hosts/categories/1'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/configuration/hosts/categories'
    Then the response code should be "200"
    And the json node "result" should have 0 elements


  Scenario: Create a host category
    Given I am logged in

    When I send a POST request to '/api/latest/configuration/hosts/categories' with body:
    """
    {
        "name": "host-cat-name",
        "alias": "host-cat-alias",
        "comments": "blablabla"
    }
    """
    Then the response code should be "201"
    And the JSON should be equal to:
    """
    {
        "id": 1,
        "name": "host-cat-name",
        "alias": "host-cat-alias",
        "is_activated": true,
        "comments": "blablabla"
    }
    """