Feature:
  In order to manage API tokens
  As a logged in user
  I want to find and edit tokens

  Background:
    Given a running instance of Centreon Web API
    And the endpoints are described in Centreon Web API documentation

  Scenario: Create token
    Given I am logged in

    # creator is admin
    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token A  ",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | name         | "my token A"     |
      | creator.name | "admin admin"    |
      | user.name    | "User"           |

    When I send a GET request to '/api/latest/administration/tokens'
    Then the response code should be "200"
    And the JSON node "result" should have "1" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"     |
      | result[0].user.id      | 18               |
      | result[0].user.name    | "User"           |
      | result[0].creator.id   | 1                |
      | result[0].creator.name | "admin admin"    |
      | result[0].is_revoked   | false            |

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token A  ",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "409"

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "400"

    # deletor is admin
    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "my-token",
        "user_id": 1,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/administration/tokens?sort_by={"token_name":"ASC"}'
    Then the response code should be "200"
    And the JSON node "result" should have "2" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"  |
      | result[0].user.id      | 18            |
      | result[0].user.name    | "User"        |
      | result[0].creator.id   | 1             |
      | result[0].creator.name | "admin admin" |
      | result[0].is_revoked   | false         |
      | result[1].name         | "my-token"    |
      | result[1].user.id      | 1             |
      | result[1].user.name    | "admin admin" |
      | result[1].creator.id   | 1             |
      | result[1].creator.name | "admin admin" |
      | result[1].is_revoked   | false         |

    When I send a DELETE request to '/api/latest/administration/tokens/my-token'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/administration/tokens'
    Then the response code should be "200"
    And the JSON node "result" should have "1" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"  |
      | result[0].user.id      | 18            |
      | result[0].user.name    | "User"        |
      | result[0].creator.id   | 1             |
      | result[0].creator.name | "admin admin" |
      | result[0].is_revoked   | false         |

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "someone-else-token",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/administration/tokens?sort_by={"token_name":"ASC"}'
    Then the response code should be "200"
    And the JSON node "result" should have "2" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"         |
      | result[0].user.id      | 18                   |
      | result[0].user.name    | "User"               |
      | result[0].creator.id   | 1                    |
      | result[0].creator.name | "admin admin"        |
      | result[0].is_revoked   | false                |
      | result[1].name         | "someone-else-token" |
      | result[1].user.id      | 18                   |
      | result[1].user.name    | "User"               |
      | result[1].creator.id   | 1                    |
      | result[1].creator.name | "admin admin"        |
      | result[1].is_revoked   | false                |

    When I send a DELETE request to '/api/latest/administration/tokens/someone-else-token'
    Then the response code should be "404"
    When I send a DELETE request to '/api/latest/administration/tokens/someone-else-token/users/18'
    Then the response code should be "204"
    When I send a DELETE request to '/api/latest/administration/tokens/unknown-token'
    Then the response code should be "404"
    When I send a DELETE request to '/api/latest/administration/tokens/unknown-token/users/18'
    Then the response code should be "404"

    When I send a GET request to '/api/latest/administration/tokens'
    Then the response code should be "200"
    And the JSON node "result" should have "1" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"         |
      | result[0].user.id      | 18                   |
      | result[0].user.name    | "User"               |
      | result[0].creator.id   | 1                    |
      | result[0].creator.name | "admin admin"        |
      | result[0].is_revoked   | false                |

    # creator is not admin, without tokens management rights
    Given the following CLAPI import data:
      """
      CONTACT;ADD;ala;ala;ala@localhost.com;Centreon@2022;0;1;en_US;local
      CONTACT;setparam;ala;reach_api;1
      ACLMENU;add;ACL Menu test;my alias
      ACLMENU;grantrw;ACL Menu test;1;Administration;API Tokens
      ACLGROUP;add;ACL Group test;my alias
      ACLGROUP;addmenu;ACL Group test;ACL Menu test
      ACLGROUP;addresource;ACL Group test;ACL Resource test
      ACLGROUP;addcontact;ACL Group test;ala
      """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token B  ",
        "user_id": 20,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | name         | "my token B"  |
      | creator.name | "ala"         |
      | user.name    | "ala"         |

    When I send a GET request to '/api/latest/administration/tokens'
    Then the response code should be "200"
    And the JSON node "result" should have "1" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token B" |
      | result[0].user.id      | 20           |
      | result[0].user.name    | "ala"        |
      | result[0].creator.id   | 20           |
      | result[0].creator.name | "ala"        |
      | result[0].is_revoked   | false        |

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token C  ",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "409"

    # deletor no admin, no token management rights
    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "my-token",
        "user_id": 20,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/administration/tokens?sort_by={"token_name":"ASC"}'
    Then the response code should be "200"
    And the JSON node "result" should have "2" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token B" |
      | result[0].user.id      | 20           |
      | result[0].user.name    | "ala"        |
      | result[0].creator.id   | 20           |
      | result[0].creator.name | "ala"        |
      | result[0].is_revoked   | false        |
      | result[1].name         | "my-token"   |
      | result[1].user.id      | 20           |
      | result[1].user.name    | "ala"        |
      | result[1].creator.id   | 20           |
      | result[1].creator.name | "ala"        |
      | result[1].is_revoked   | false        |


    When I send a DELETE request to '/api/latest/administration/tokens/my-token'
    Then the response code should be "204"

    When I send a GET request to '/api/latest/administration/tokens'
    Then the response code should be "200"
    And the JSON node "result" should have "1" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token B" |
      | result[0].user.id      | 20           |
      | result[0].user.name    | "ala"        |
      | result[0].creator.id   | 20           |
      | result[0].creator.name | "ala"        |
      | result[0].is_revoked   | false        |

    When I am logged in
    And I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "someone-else-token",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"

    When I send a GET request to '/api/latest/administration/tokens?sort_by={"token_name":"ASC"}'
    Then the response code should be "200"
    And the JSON node "result" should have "3" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"         |
      | result[0].user.id      | 18                   |
      | result[0].user.name    | "User"               |
      | result[0].creator.id   | 1                    |
      | result[0].creator.name | "admin admin"        |
      | result[0].is_revoked   | false                |
      | result[1].name         | "my token B"         |
      | result[1].user.id      | 20                   |
      | result[1].user.name    | "ala"                |
      | result[1].creator.id   | 20                   |
      | result[1].creator.name | "ala"                |
      | result[1].is_revoked   | false                |
      | result[2].name         | "someone-else-token" |
      | result[2].user.id      | 18                   |
      | result[2].user.name    | "User"               |
      | result[2].creator.id   | 1                    |
      | result[2].creator.name | "admin admin"        |
      | result[2].is_revoked   | false                |

    When I am logged in with "ala"/"Centreon@2022"
    And I send a DELETE request to '/api/latest/administration/tokens/someone-else-token'
    Then the response code should be "404"
    When I send a DELETE request to '/api/latest/administration/tokens/someone-else-token/users/18'
    Then the response code should be "400"
    When I send a DELETE request to '/api/latest/administration/tokens/unknown-token'
    Then the response code should be "404"
    When I send a DELETE request to '/api/latest/administration/tokens/unknown-token/users/18'
    Then the response code should be "404"

    # creator is not admin, with tokens management rights
    Given the following CLAPI import data:
      """
      ACLACTION;add;ACL Action test;my alias
      ACLACTION;grant;ACL Action Test;manage_tokens
      ACLGROUP;addaction;ACL Group test;ACL Action test
      """
    And I am logged in with "ala"/"Centreon@2022"

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token C  ",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"
    And the JSON nodes should be equal to:
      | name         | "my token C" |
      | creator.name | "ala"        |
      | user.name    | "User"       |

    When I send a GET request to '/api/latest/administration/tokens?sort_by={"token_name":"ASC"}'
    Then the response code should be "200"
    And the JSON node "result" should have "4" element
    And the JSON nodes should be equal to:
      | result[0].name         | "my token A"         |
      | result[0].user.id      | 18                   |
      | result[0].user.name    | "User"               |
      | result[0].creator.id   | 1                    |
      | result[0].creator.name | "admin admin"        |
      | result[0].is_revoked   | false                |
      | result[1].name         | "my token B"         |
      | result[1].user.id      | 20                   |
      | result[1].user.name    | "ala"                |
      | result[1].creator.id   | 20                   |
      | result[1].creator.name | "ala"                |
      | result[1].is_revoked   | false                |
      | result[2].name         | "my token C"         |
      | result[2].user.id      | 18                   |
      | result[2].user.name    | "User"               |
      | result[2].creator.id   | 20                   |
      | result[2].creator.name | "ala"                |
      | result[2].is_revoked   | false                |
      | result[3].name         | "someone-else-token" |
      | result[3].user.id      | 18                   |
      | result[3].user.name    | "User"               |
      | result[3].creator.id   | 1                    |
      | result[3].creator.name | "admin admin"        |
      | result[3].is_revoked   | false                |

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token C  ",
        "user_id": 18,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "409"

    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "  my token C  ",
        "user_id": 20,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"

    # deletor not admin, with management tokens rights
    When I send a POST request to '/api/latest/administration/tokens' with body:
      """
      {
        "name": "my-token",
        "user_id": 20,
        "expiration_date": "2123-08-31T15:46:00+02:00"
      }
      """
    Then the response code should be "201"
    When I send a DELETE request to '/api/latest/administration/tokens/my-token'
    Then the response code should be "204"

    When I send a DELETE request to '/api/latest/administration/tokens/someone-else-token'
    Then the response code should be "404"
    When I send a DELETE request to '/api/latest/administration/tokens/someone-else-token/users/18'
    Then the response code should be "204"
    When I send a DELETE request to '/api/latest/administration/tokens/unknown-token'
    Then the response code should be "404"
    When I send a DELETE request to '/api/latest/administration/tokens/unknown-token/users/18'
    Then the response code should be "404"
