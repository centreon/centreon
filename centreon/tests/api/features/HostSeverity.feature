Feature:
  In order to check the host severities
  As a logged in user
  I want to find host severities using api

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Host severities listing as admin
    Given I am logged in
    And the following CLAPI import data:
    """
    HC;ADD;severity1;host-severity-alias
    HC;setparam;severity1;hc_comment;blabla bla
    HC;setparam;severity1;hc_activate;1
    HC;setseverity;severity1;42;logos/centreon.png
    """

    When I send a GET request to '/api/latest/configuration/hosts/severities'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "severity1",
                "alias": "host-severity-alias",
                "level": 42,
                "icon_id": 1,
                "is_activated": true,
                "comments": "blabla bla"
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

  Scenario: Host categories listing as non-admin with ACL filters
    Given the following CLAPI import data:
    """
    HC;ADD;host-sev1;host-sev1-alias
    HC;setseverity;host-sev1;1;logos/centreon.png
    HC;ADD;host-sev2;host-sev2-alias
    HC;setseverity;host-sev2;2;logos/centreon.png
    CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Hosts;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLRESOURCE;addfilter_hostcategory;ACL Resource test;host-sev2
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/severities'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 2,
                "name": "host-sev2",
                "alias": "host-sev2-alias",
                "level": 2,
                "icon_id": 1,
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

  Scenario: Host categories listing as non-admin without ACL filters
    Given the following CLAPI import data:
    """
    HC;ADD;host-sev1;host-sev1-alias
    HC;setseverity;host-sev1;1;logos/centreon.png
    HC;ADD;host-sev2;host-sev2-alias
    HC;setseverity;host-sev2;2;logos/centreon.png
    CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
    CONTACT;setparam;ala;reach_api;1
    ACLMENU;add;ACL Menu test;my alias
    ACLMENU;grantro;ACL Menu test;1;Configuration;Hosts;Categories
    ACLRESOURCE;add;ACL Resource test;my alias
    ACLGROUP;add;ACL Group test;my alias
    ACLGROUP;addmenu;ACL Group test;ACL Menu test
    ACLGROUP;addresource;ACL Group test;ACL Resource test
    ACLGROUP;addcontact;ACL Group test;ala
    """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a GET request to '/api/latest/configuration/hosts/severities'
    Then the response code should be "200"
    And the JSON should be equal to:
    """
    {
        "result": [
            {
                "id": 1,
                "name": "host-sev1",
                "alias": "host-sev1-alias",
                "level": 1,
                "icon_id": 1,
                "is_activated": true,
                "comments": null
            },
            {
                "id": 2,
                "name": "host-sev2",
                "alias": "host-sev2-alias",
                "level": 2,
                "icon_id": 1,
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