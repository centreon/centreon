const axios = require("axios");
const fs = require("fs");
const { execSync } = require("child_process");
const os = require("os");

async function get_xray_token(client_id, client_secret) {
  const data = {
    client_id: client_id,
    client_secret: client_secret,
  };

  try {
    const response = await axios.post(
      "https://xray.cloud.getxray.app/api/v1/authenticate",
      data,
      {
        headers: { "Content-Type": "application/json" },
      }
    );

    if (response.status === 200) {
      const token = response.headers["x-access-token"];
      if (token) {
        console.log("Authentication successful");
        return token;
      } else {
        console.log(
          "Authentication failed. Token not found in the response headers."
        );
        return null;
      }
    } else {
      console.log("Authentication failed with status code: ", response.status);
      return null;
    }
  } catch (error) {
    console.log("Authentication failed");
    console.error(error);
    return null;
  }
}

// Constants
const JIRA_USER = process.env.JIRA_USER;
const JIRA_TOKEN_TEST = process.env.JIRA_TOKEN_TEST;
const CLIENT_ID = process.env.CLIENT_ID;
const CLIENT_SECRET = process.env.CLIENT_SECRET;

const XRAY_API_URL =
  "https://xray.cloud.getxray.app/api/v2/import/feature?projectKey=MON";
const GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql";

function get_target_version(version_number) {
  const target_version = `OnPrem - ${version_number}`;
  console.log(`Target Versions: ${target_version}`);
  return [target_version];
}

function extract_data_from_feature_file(file_path) {
  try {
    const feature_file_content = fs.readFileSync(file_path, "utf-8");
    const match = feature_file_content.match(/#testSet:(.*?)$/ms);

    if (match) {
      const test_set_key = match[1].trim();
      return test_set_key;
    }
  } catch (error) {
    console.log(`Error reading feature file: ${error}`);
  }

  return null;
}

async function get_jira_issue_id(test_set_key) {
  if (test_set_key) {
    try {
      const response = await axios.get(
        `https://centreon.atlassian.net/rest/api/2/issue/${test_set_key}`,
        {
          headers: {
            Accept: "application/json",
          },
          auth: {
            username: JIRA_USER,
            password: JIRA_TOKEN_TEST,
          },
        }
      );

      if (response.status === 200) {
        const jira_data = response.data;
        const test_set_id = jira_data.id;
        console.log("the Id of the testSet is: " + test_set_id);
        return test_set_id;
      } else {
        console.log(
          `Jira API Request Failed with Status Code: ${response.status}`
        );
        console.log(response.data);
      }
    } catch (error) {
      console.log(`Error fetching Jira issue: ${error}`);
    }
  } else {
    console.log("No Test Set Indicated");
  }

  return null;
}

async function upload_feature_file_to_xray(feature_file_path, XRAY_TOKEN) {
  try {
    const formData = new FormData();
    formData.append("file", fs.createReadStream(feature_file_path));

    console.log("XRAY_TOKEN ", XRAY_TOKEN);

    const response = await axios.post(XRAY_API_URL, formData, {
      headers: {
        "Content-Type": "multipart/form-data",
        Authorization: `Bearer ${XRAY_TOKEN}`,
      },
    });

    console.log("Feature file uploaded successfully to Xray.");
    console.log(response.data);

    const json_start = response.data.indexOf("{");
    const json_response = response.data.substr(json_start);

    const response_data = JSON.parse(json_response);
    return response_data;
  } catch (error) {
    console.log(`Error uploading feature file to Xray: ${error}`);
    return null;
  }
}

async function update_jira_issues(
  test_selfs,
  target_versions,
  components_list
) {
  for (const api of test_selfs) {
    try {
      // Get the existing issue data
      const response = await axios.get(api, {
        headers: {
          Accept: "application/json",
        },
        auth: {
          username: JIRA_USER,
          password: JIRA_TOKEN_TEST,
        },
      });

      if (response.status === 200) {
        const existing_issue_data = response.data;
        const existing_customfield_10901 =
          existing_issue_data.fields.customfield_10901 || [];

        // Extract the existing values or initialize as an empty list if null
        const existing_values = existing_customfield_10901
          ? existing_customfield_10901.map((item) => item.value)
          : [];
        console.log("Existing values of version: ", existing_values);

        for (const target_version of target_versions) {
          if (!existing_values.includes(target_version)) {
            existing_values.push(target_version);
          }
        }
        console.log("the new target versions are: ", existing_values);

        const issue_update_payload = {
          fields: {
            customfield_10901: existing_values.map((value) => ({
              value: value,
            })),
          },
        };
        console.log("the issue update is : ", issue_update_payload);

        if (components_list) {
          issue_update_payload.fields.components = components_list.map(
            (component) => ({ name: component })
          );
        } else {
          console.log("No component mentioned");
        }

        const jira_response = await axios.put(api, issue_update_payload, {
          headers: {
            Accept: "application/json",
          },
          auth: {
            username: JIRA_USER,
            password: JIRA_TOKEN_TEST,
          },
        });

        if (jira_response.status === 204) {
          console.log(`Issue ${api} updated successfully in Jira.`);
        } else {
          console.log(
            `Error updating issue ${api} in Jira. Status code: ${jira_response.status}`
          );
          console.log(jira_response.data);
        }
      }
    } catch (error) {
      console.log(`Error updating Jira issue: ${error}`);
    }
  }
}

async function main() {
  // Check for the correct number of command-line arguments
  if (process.argv.length !== 5) {
    console.log(
      "Usage: node test_creation_xray.js <feature_file> <branch_ref> <version_number>"
    );
    process.exit(1);
  }

  const FEATURE_FILE_PATH = process.argv[2];
  const branch_name = process.argv[3];
  const version_number = process.argv[4];

  console.log(
    `Running script for ${FEATURE_FILE_PATH} on branch ${branch_name}`
  );

  const XRAY_TOKEN = await get_xray_token(CLIENT_ID, CLIENT_SECRET);

  // Upload the feature file to Xray
  const response_data = await upload_feature_file_to_xray(
    FEATURE_FILE_PATH,
    XRAY_TOKEN
  )
  console.log("response data", response_data);

  // Set the target version based on the version_number
  const target_versions =
    branch_name === "develop"
      ? get_target_version(version_number).concat("Cloud")
      : get_target_version(version_number);

  const test_set_key = extract_data_from_feature_file(FEATURE_FILE_PATH);

  // Uploading the feature file to Xray succeed
  if (response_data) {
    // Getting the API and the Ids of the created issues ( tests )
    const test_selfs = response_data.updatedOrCreatedTests?.map(
      (test) => test.self
    );
    const test_ids = response_data.updatedOrCreatedTests?.map(
      (test) => test.id
    );

    // Updating the Issues to match the target version and components
    // For now we have only centreon-web
    update_jira_issues(test_selfs, target_versions, ["centreon-web"]);

    if (test_set_key) {
      const test_set_id = get_jira_issue_id(test_set_key);
      if (test_set_id) {
        const variables = {
          test_set_id: test_set_id,
          test_ids: test_ids,
        };

        const headers = {
          Authorization: `Bearer ${XRAY_TOKEN}`,
          "Content-Type": "application/json",
        };

        const graphql_request = {
          query: `
                        mutation AddTestsToTestSet ($test_set_id: String!, $test_ids: [String!]!) {
                            addTestsToTestSet(
                                issueId: $test_set_id,
                                testIssueIds: $test_ids
                            ) {
                                addedTests
                                warning
                            }
                        }
                    `,
          variables: variables,
        };

        axios
          .post(GRAPHQL_URL, graphql_request, {
            headers: headers,
          })
          .then((response) => {
            if (response.status === 200) {
              console.log(`Warning: ${response.data}`);
            } else {
              console.log(
                `GraphQL Request Failed with Status Code: ${response.status}`
              );
              console.log(response.data);
            }
          })
          .catch((error) => {
            console.log(`GraphQL Request Failed: ${error}`);
          });
      } else {
        console.log(`No test set found having this key : ${test_set_key}`);
      }
    } else {
      console.log("No test set mentioned");
    }
  }
  // Uploading the feature file to Xray failed
  else {
    console.log("Upload feature file to Xray Failed !");
  }
}

if (require.main === module) {
  main();
}
