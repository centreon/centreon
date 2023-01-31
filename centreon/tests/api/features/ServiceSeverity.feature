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
    SC;setseverity;severity1;42;logos/centreon.png
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

  Scenario: Service categories listing as non-admin with ACL filters
    Given the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/centreon.png
    SC;ADD;service-sev2;service-sev2-alias
    SC;setseverity;service-sev2;2;logos/centreon.png
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

  Scenario: Service categories listing as non-admin without ACL filters
    Given the following CLAPI import data:
    """
    SC;ADD;service-sev1;service-sev1-alias
    SC;setseverity;service-sev1;1;logos/centreon.png
    SC;ADD;service-sev2;service-sev2-alias
    SC;setseverity;service-sev2;2;logos/centreon.png
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
