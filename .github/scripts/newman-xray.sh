           xray_graphql_getTestPlan='{
              "query": "query GetTestPlan($issueId: String) { getTestPlan(issueId: $issueId) { issueId tests(limit: 100) { results { issueId testType { name } } } } }",
              "variables": {
                "issueId": "73560"
              }
            }'

            response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer ${{ steps.generate-xray-token.outputs.xray_token }}" --data "${xray_graphql_getTestPlan}" "https://xray.cloud.getxray.app/api/v2/graphql")

            echo "Response from Get Test Plan:"
            echo "$response"

            # Parsing and processing tests id
            issue_ids=($(echo "$response" | jq -r '.data.getTestPlan.tests.results[].issueId'))
            summaries=()

            for issue_id in "${issue_ids[@]}"; do
              echo "Processing issue ID: $issue_id"
              jira_issue_url="https://centreon.atlassian.net/rest/api/2/issue/$issue_id"

              response=$(curl --request GET \
                --url "$jira_issue_url" \
                --user "${{ secrets.jira_user }}:${{ secrets.jira_token_test }}" \
                --header 'Accept: application/json')

              summary=$(echo "$response" | jq -r '.fields.summary')

              if [ "$response_code" -eq 404 ]; then                e
               echo "The issue with ID $issue_id does not exist or you do not have permission to see it."
                break
              else
                echo "The issue with ID $issue_id exists."
                summaries+=("$summary")
              fi
            done

            collections=($(find ./collections -type f -name "*.postman_collection.json"))
            test_case_ids=()

            xray_graphql_AddingTestsToTestPlan='{
              "query": "mutation AddTestsToTestPlan($issueId: String!, $testIssueIds: [String]!) { addTestsToTestPlan(issueId: $issueId, testIssueIds: $testIssueIds) { addedTests warning } }",
              "variables": {
                "issueId": "73560",
                "testIssueIds": []
              }
            }'
            for collection_file in "${collections[@]}"; do
              collection_name=$(basename "$collection_file" .postman_collection.json)
              collection_name_sanitized="${collection_name//[^a-zA-Z0-9]/_}"

              if [[ " ${summaries[@]} " =~ " ${collection_name_sanitized} " ]]; then
                echo "The test case for $collection_name_sanitized already exists in the test plan."
              else
                # Ajouter un cas de test
                response=$(curl --request POST \
                  --url 'https://centreon.atlassian.net/rest/api/2/issue' \
                  --user '${{ secrets.jira_user }}:${{ secrets.jira_token_test }}' \
                  --header 'Accept: application/json' \
                  --header 'Content-Type: application/json' \
                  --data '{
                    "fields": {
                      "project": {
                        "key": "MON"
                      },
                      "summary": "'"$collection_name_sanitized"'",
                      "description": "Test case for '"$collection_name_sanitized"'",
                      "issuetype": {
                        "name": "Test"
                      }
                    }
                  }' \
                  --max-time 20)

                sleep 5

                if [ -z "$response" ]; then
                  echo "Failed to create the test case within the specified time."
                else
                  test_case_id=$(echo "$response" | jq -r '.id')
                  test_case_ids+=("$test_case_id")  # Ajouter l'ID du cas de test à la liste

                  echo "Created Test Case with ID: $test_case_id"
                  # Ajouter le nom du cas de test à la liste des résumés
                  summaries+=("$collection_name_sanitized")

                  # Update GraphQL query to add this test to the test plan
                  xray_graphql_AddingTestsToTestPlan_variables=$(echo "$xray_graphql_AddingTestsToTestPlan" | jq --arg test_case_id "$test_case_id" '.variables.testIssueIds += [$test_case_id]')

                  # Execute GraphQL mutation to add tests to the test plan
                  response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer ${{ steps.generate-xray-token.outputs.xray_token}}" --data "$xray_graphql_AddingTestsToTestPlan_variables" "https://xray.cloud.getxray.app/api/v2/graphql")
                fi
              fi
            done

            response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer ${{ steps.generate-xray-token.outputs.xray_token }}" --data "${xray_graphql_getTestPlan}" "https://xray.cloud.getxray.app/api/v2/graphql")
            issue_list_ids=($(echo "$response" | jq -r '.data.getTestPlan.tests.results[].issueId'))


            test_issue_ids=("${issue_list_ids[@]}")
            formatted_test_issue_ids_str="["
            for issue_id in "${issue_list_ids[@]}"; do
              formatted_test_issue_ids_str+="\"$issue_id\","
            done
            formatted_test_issue_ids_str="${formatted_test_issue_ids_str%,}" # Supprime la dernière virgule
            formatted_test_issue_ids_str+="]"
            echo "$formatted_test_issue_ids_str"

            xray_graphql_createTestExecution='{
              "query": "mutation CreateTestExecution($testIssueIds: [String], $jira: JSON!) { createTestExecution(testIssueIds: $testIssueIds, jira: $jira) { testExecution { issueId jira(fields: [\"key\"]) } warnings createdTestEnvironments } }",
              "variables": {
                "testIssueIds": '"$formatted_test_issue_ids_str"',
                "jira": {
                  "fields": {
                    "summary": "Test Execution for newman collection testplan",
                    "project": { "key": "MON" }
                  }
                }
              }
            }'

            response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer ${{ steps.generate-xray-token.outputs.xray_token}}" --data "$xray_graphql_createTestExecution" -v "https://xray.cloud.getxray.app/api/v2/graphql")

            echo "Response from Create Test Execution:"
            echo "$response"

            # Extract the ID of the new test run
            test_execution_id=$(echo "$response" | jq -r '.data.createTestExecution.testExecution.issueId')

            # Check if ID is null or not
            if [ "$test_execution_id" == "null" ]; then
              echo "Failed to create the Test Execution. Check the response for errors."
            else
              echo "Created Test Execution with ID: $test_execution_id"
              echo "test_exec=$test_execution_id" >> $GITHUB_OUTPUT
              fi

            xray_graphql_addTestExecutionsToTestPlan='{
              "query": "mutation AddTestExecutionsToTestPlan($issueId: String!, $testExecIssueIds: [String]!) { addTestExecutionsToTestPlan(issueId: $issueId, testExecIssueIds: $testExecIssueIds) { addedTestExecutions warning } }",
              "variables": {
                "issueId": "73560",
                "testExecIssueIds": ["'$test_execution_id'"]
              }
            }'
            response=$(curl -X POST -H "Content-Type: application/json" -H "Authorization: Bearer ${{ steps.generate-xray-token.outputs.xray_token}}" --data "$xray_graphql_addTestExecutionsToTestPlan" -v "https://xray.cloud.getxray.app/api/v2/graphql")