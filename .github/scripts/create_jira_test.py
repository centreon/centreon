import re
import subprocess
import json
import requests
import os
import sys

def get_xray_token(client_id, client_secret):
    # Define the curl command
    curl_command = [
        "curl",
        "-H", "Content-Type: application/json",
        "-X", "POST",
        "--data", f'{{ "client_id": "{client_id}", "client_secret": "{client_secret}" }}',
        "https://xray.cloud.getxray.app/api/v1/authenticate"
    ]

    try:
        result = subprocess.check_output(curl_command, stderr=subprocess.STDOUT, text=True)
        print("Authentication successful")
        # Split the response to extract the Xray token
        xray_token = result.strip().split('"')[1]  # Extract the token part
        return xray_token
    except subprocess.CalledProcessError as e:
        print("Authentication failed")
        print(e.output)
        return None

# Constants
JIRA_USER = os.environ.get('JIRA_USER')
JIRA_TOKEN_TEST = os.environ.get('JIRA_TOKEN_TEST')
CLIENT_ID = "DB1C58D167BC4BE798DCFB8CA1C712B9"
CLIENT_SECRET = "296443a35f9c668c86c362f57d3b87ef9327d75825d3aa7f22426bcaf9e874cf"

XRAY_TOKEN = get_xray_token(CLIENT_ID,CLIENT_SECRET)

XRAY_API_URL = "https://xray.cloud.getxray.app/api/v2/import/feature?projectKey=MON"
GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql"

def get_target_version(version_number):
    target_version = f'OnPrem - {version_number}'
    print(f"Target Versions: {target_version}")
    return [target_version]

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
        match = re.search(r'#testSet:(.*?)$', feature_file_content, re.MULTILINE | re.DOTALL)

        if match:
            test_set_key = match.group(1).strip()
            return test_set_key

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

def update_jira_issues(test_selfs, target_versions, components_list):
    for api in test_selfs:
        try:
            # Get the existing issue data
            jira_response = requests.get(api, headers={
                "Accept": "application/json"
            }, auth=(JIRA_USER, JIRA_TOKEN_TEST))

            if jira_response.status_code == 200:
                existing_issue_data = jira_response.json()
                existing_customfield_10901 = existing_issue_data['fields'].get('customfield_10901', [])

                # Extract the existing values or initialize as an empty list if null
                existing_values = [item['value'] for item in existing_customfield_10901] if existing_customfield_10901 else []

                print("Existing values of version: ", existing_values)

                # Add the target_versions if they don't exist
                for target_version in target_versions:
                    if target_version not in existing_values:
                        existing_values.append(target_version)

                print("the new target versions are: ", existing_values)

                issue_update_payload = {
                    "fields": {
                        "customfield_10901": [{"value": value} for value in existing_values]
                    }
                }

                print("the issue update is : ", issue_update_payload)

                if components_list:
                    issue_update_payload["fields"]["components"] = [{"name": component} for component in components_list]
                else:
                    print("No component mentioned")

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
    if len(sys.argv) != 4:
        print("Usage: python test_creation_xray.py <feature_file> <branch_ref>")
        sys.exit(1)

    FEATURE_FILE_PATH = sys.argv[1]
    branch_name = sys.argv[2]
    version_number = sys.argv[3]

    print(f"Running script for {FEATURE_FILE_PATH} on branch {branch_name}")

    # Upload the feature file to Xray
    response_data = upload_feature_file_to_xray(FEATURE_FILE_PATH)
    print("response data",response_data)

    # Set the target version based on the version_number
    if(branch_name=='develop'):
        target_versions = get_target_version(version_number).append('Cloud')
    else:
        target_versions = get_target_version(version_number)


    test_set_key = extract_data_from_feature_file(FEATURE_FILE_PATH)

    # Uploading the feature file to Xray succeed
    if response_data :
        # Getting the API and the Ids of the created issues ( tests )
        test_selfs = [test['self'] for test in response_data['updatedOrCreatedTests']]
        test_ids = [test['id'] for test in response_data['updatedOrCreatedTests']]
                
        # Updating the Issues to match the target version and components
        # For now we have only centreon-web
        update_jira_issues(test_selfs, target_versions, ["centreon-web"])

        if test_set_key : 
            test_set_id = get_jira_issue_id(test_set_key)
            if test_set_id:
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

            else:
                print(f"No test set found having this key : {test_set_key}")
        else:
            print("No test set mentioned") 
    # Uploading the feature file to Xray failed
    else : 
        print("Upload feature file to Xray Failed !")

if __name__ == "__main__":
    main()
