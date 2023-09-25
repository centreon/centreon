import re
import subprocess
import json
import requests
import os
import sys


# Constants
JIRA_USER = os.environ.get('JIRA_USER')
JIRA_TOKEN_TEST = os.environ.get('JIRA_TOKEN_TEST')
XRAY_TOKEN = os.environ.get('XRAY_TOKEN')

XRAY_API_URL = "https://xray.cloud.getxray.app/api/v2/import/feature?projectKey=MON"
GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql"

branch_to_target = {
    'develop': 'Cloud',
    'dev-23.04.x': 'OnPrem - 23.04',
    'dev-22.10.x': 'OnPrem - 22.10',
    'dev-22.04.x': 'OnPrem - 22.04',
    'dev-21.10.x': 'OnPrem - 21.10',
    'MON-20381-Integrate-E2E-tests-from-GHA-to-XRay' : 'Cloud'
    # Add more mappings as needed
}

def get_modified_feature_files():
    try:
        git_diff_command = f"git diff --name-only origin/main...{os.environ.get('GITHUB_REF')} -- '**/*.feature'"
        result = subprocess.check_output(git_diff_command, shell=True, stderr=subprocess.STDOUT, text=True)
        return result.strip().split('\n')
    except subprocess.CalledProcessError as e:
        print(f"Error getting modified feature files: {e}")
        return []

def extract_data_from_feature_file(file_path):
    try:
        feature_file_content = open(file_path, "r").read()
        match = re.search(r'#components:(.*?)\s+testSet:(.*?)$', feature_file_content, re.MULTILINE | re.DOTALL)

        if match:
            components = match.group(1).strip()
            test_set_key = match.group(2).strip()

            return components, test_set_key

    except Exception as e:
        print(f"Error reading feature file: {e}")

    return None, None

def get_jira_issue_id(test_set_key):
    if test_set_key:
        try:
            jira_response = requests.get(f"https://centreon.atlassian.net/rest/api/2/issue/{test_set_key}", headers={
                "Accept": "application/json"
            }, auth=(JIRA_USER, JIRA_TOKEN_TEST))

            if jira_response.status_code == 200:
                jira_data = jira_response.json()
                test_set_id = jira_data.get("id")
                return test_set_id
            else:
                print(f"Jira API Request Failed with Status Code: {jira_response.status_code}")
                print(jira_response.text)
        except Exception as e:
            print(f"Error fetching Jira issue: {e}")
    else:
        print("No Test Set Indicated")

    return None

def upload_feature_file_to_xray(feature_file_path):
    try:
        curl_command = [
            "curl",
            "-H", "Content-Type: multipart/form-data",
            "-X", "POST",
            "-H", f"Authorization: Bearer {XRAY_TOKEN}",
            "-F", f"file=@{feature_file_path}",
            XRAY_API_URL
        ]

        result = subprocess.check_output(curl_command, stderr=subprocess.STDOUT, text=True)
        print("Feature file uploaded successfully to Xray.")
        print(result)

        json_start = result.find('{')
        json_response = result[json_start:]

        response_data = json.loads(json_response)
        return response_data

    except subprocess.CalledProcessError as e:
        print(f"Error uploading feature file to Xray: {e}")
        return None

def update_jira_issues(test_selfs, target_version, components_list):
    for api in test_selfs:
        try:
            jira_response_get = requests.get(
                api,
                headers={
                    "Accept": "application/json"
                },
                auth=(JIRA_USER, JIRA_TOKEN_TEST)
            )

            issue_update_payload = {
                "fields": {
                    "customfield_10901": [{"value": target_version}],
                    "components": [{"name": component} for component in components_list]
                }
            }

            jira_response = requests.put(api, headers={
                "Accept": "application/json"
            }, json=issue_update_payload, auth=(JIRA_USER, JIRA_TOKEN_TEST))

            if jira_response.status_code == 204:
                print(f"Issue {api} updated successfully in Jira.")
            else:
                print(f"Error updating issue {api} in Jira. Status code: {jira_response.status_code}")
                print(jira_response.text)

        except Exception as e:
            print(f"Error updating Jira issue: {e}")

def main():
    # Check for the correct number of command-line arguments
    if len(sys.argv) != 3:
        print("Usage: python test_creation_xray.py <feature_file> <branch_ref>")
        sys.exit(1)

    FEATURE_FILE_PATH = sys.argv[1]
    branch_ref = sys.argv[2]
      # Extract the branch name from the branch_ref
    branch_name = branch_ref.split('/')[-1]  # Get the last part of the ref

    print(f"Running script for {FEATURE_FILE_PATH} on branch {branch_name}")

    # Set the target version based on the branch name
    target_version = branch_to_target.get(branch_name, '')
    print(f"Target Versions: {target_version}")

    components, test_set_key = extract_data_from_feature_file(FEATURE_FILE_PATH)

    if  components:
        components_list = components.split(',')
        print(f"Components: {components_list}")
        print(f"Test Set: {test_set_key}")

        response_data = upload_feature_file_to_xray(FEATURE_FILE_PATH)

        print("response data",response_data)

        test_set_id = get_jira_issue_id(test_set_key) 
        if test_set_id:
            if response_data:
                test_selfs = [test['self'] for test in response_data['updatedOrCreatedTests']]
                test_ids = [test['id'] for test in response_data['updatedOrCreatedTests']]

                update_jira_issues(test_selfs, target_version, components_list)

                variables = {
                    "test_set_id": test_set_id,
                    "test_ids": test_ids
                }

                headers = {
                    "Authorization": f"Bearer {XRAY_TOKEN}",
                    "Content-Type": "application/json",
                }

                graphql_request = {
                    "query": """
                        mutation AddTestsToTestSet ($test_set_id: String!, $test_ids: [String!]!) {
                            addTestsToTestSet(
                                issueId: $test_set_id,
                                testIssueIds: $test_ids
                            ) {
                                addedTests
                                warning
                            }
                        }
                    """,
                    "variables": variables,
                }

                response = requests.post(GRAPHQL_URL, json=graphql_request, headers=headers)

                if response.status_code == 200:
                    result = response.json()
                    print(f"Warning: {result}")
                else:
                    print(f"GraphQL Request Failed with Status Code: {response.status_code}")
                    print(response.text)

if __name__ == "__main__":
    main()
