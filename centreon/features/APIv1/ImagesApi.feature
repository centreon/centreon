#features/ImagesApi.feature
@api
Feature: Check health of the Image APIs
  As an authorized user via the token
  I need to ensure my API handles proper actions and returns proper results

  Background:
    Given a Centreon server
    And I have a running instance of Centreon API

  @image
  Scenario: Healthcheck of Image APIs
    # List
    When I make a GET request to "/api/index.php?object=centreon_images&action=list"
    Then the response code should be 200
    And the response has a "result" property
    And the response has a "status" property
    And the property "result" has value
    """
    {
        "pagination":{
            "total":1,
            "offset":0,
            "limit":1
        },
        "entities":[
            {"id":1,"name":"centreon","preview":"img\/media\/logos\/logo-centreon-colors.png"}
        ]}
    """