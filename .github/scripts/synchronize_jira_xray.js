const axios = require("axios");
const fs = require("fs");
const FormData = require("form-data");
const core = require("@actions/core");

const JIRA_USER = process.env.JIRA_USER;
const JIRA_TOKEN_TEST = process.env.JIRA_TOKEN_TEST;
const CLIENT_ID = process.env.CLIENT_ID;
const CLIENT_SECRET = process.env.CLIENT_SECRET;

const XRAY_API_URL =
  "https://xray.cloud.getxray.app/api/v2/import/feature?projectKey=MON";
const GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql";

async function getXrayToken(clientId, clientSecret) {
  const data = { client_id: clientId, client_secret: clientSecret };
  try {
    const response = await axios.post(
      "https://xray.cloud.getxray.app/api/v1/authenticate",
      data,
      {
        headers: { "Content-Type": "application/json" },
      }
    );

    if (response.status !== 200) {
      core.error(`Authentication failed with status code: ${response.status}`);
      return;
    }

    const token = response.headers["x-access-token"];
    if (!token) {
      core.error(
        "Authentication failed. Token not found in the response headers."
      );
      return;
    }
    core.debug("Authentication successful");
    return token;
  } catch (error) {
    core.error(`Error Authentication: ${error}`);
  }
  return null;
}

function getTargetVersion(version_number) {
  const targetVersion = `OnPrem - ${version_number}`;
  core.debug(`Target Versions: ${targetVersion}`);
  return [targetVersion];
}

async function extractDataFromFeatureFile(file_path) {
  try {
    const feature_file_content = fs.readFileSync(file_path, "utf-8");
    const match = feature_file_content.match(/#testSet:(.*?)$/ms);

    if (match) {
      const testSetKey = match[1].trim();
      return testSetKey;
    }
  } catch (error) {
    core.error(`Error reading feature file: ${error}`);
  }
  return null;
}

async function getJiraIssueId(testSetKey) {
  if (!testSetKey) {
    core.warning("No Test Set Indicated");
    return null;
  }

  try {
    const response = await axios.get(
      `https://centreon.atlassian.net/rest/api/2/issue/${testSetKey}`,
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

    if (response.status !== 200) {
      core.error(
        `Jira API Request Failed with Status Code: ${response.status}`
      );
      core.debug(response.data);
      return;
    }

    core.debug(`The ID of the testSet is: ${response.data.id}`);
    return response.data.id;
  } catch (error) {
    core.error(`Error fetching Jira issue: ${error}`);
  }

  return null;
}

async function uploadFeatureFileToXray(featureFilePath, XRAY_TOKEN) {
  try {
    const formData = new FormData();
    formData.append("file", fs.createReadStream(featureFilePath));

    const response = await axios.post(XRAY_API_URL, formData, {
      headers: {
        ...formData.getHeaders(),
        Authorization: `Bearer ${XRAY_TOKEN}`,
      },
    });
    if (response.status !== 200) {
      core.error(
        `Feature File Upload to Xray Failed with Status Code: ${response.status}`
      );
      core.debug(response.data);
      return;
    }
    core.debug("Feature file uploaded successfully to Xray.");
    core.debug(response.data);
    return response.data;
  } catch (error) {
    core.error(`Error uploading feature file to Xray: ${error}`);
  }

  return null;
}

async function updateJiraIssues(testSelfs, targetVersions, componentsList) {
  for (const api of testSelfs) {
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

      if (response.status !== 200) {
        core.error(
          `Jira Issue Update Failed with Status Code: ${response.status}`
        );
        core.debug(response.data);
        return;
      }

      const existingIssueData = response.data;
      const existingCustomField10901 =
        existingIssueData.fields.customfield_10901 || [];
      const existingValues = existingCustomField10901
        ? existingCustomField10901.map((item) => item.value)
        : [];

      core.debug("Existing values of version: ", existingValues);

      for (const targetVersion of targetVersions) {
        if (!existingValues.includes(targetVersion)) {
          existingValues.push(targetVersion);
        }
      }
      core.debug("the new target versions are: ", existingValues);

      const issueUpdatePayload = {
        fields: {
          customfield_10901: existingValues.map((value) => ({
            value: value,
          })),
        },
      };
      core.debug(
        `the issue update for ${api} is: `,
        JSON.stringify(issueUpdatePayload)
      );

      if (componentsList) {
        issueUpdatePayload.fields.components = componentsList.map(
          (component) => ({ name: component })
        );
      } else {
        core.warning("No component mentioned");
      }

      const jira_response = await axios.put(api, issueUpdatePayload, {
        headers: {
          Accept: "application/json",
        },
        auth: {
          username: JIRA_USER,
          password: JIRA_TOKEN_TEST,
        },
      });

      if (jira_response.status !== 204) {
        core.error(
          `Error updating issue ${api} in Jira. Status code: ${jira_response.status}`
        );
        core.debug(jira_response.data);
        return;
      }

      core.debug(`Issue ${api} updated successfully in Jira.`);
    } catch (error) {
      core.error(`Error updating Jira issue: ${error}`);
    }
  }
}

async function main() {
  // Check for the correct number of command-line arguments
  if (process.argv.length !== 5) {
    core.error(
      "Usage: node synchronize_jira_xray.js <feature_file> <branch_ref> <version_number>"
    );
    process.exit(1);
  }

  const FEATURE_FILE_PATH = process.argv[2];
  const branch_name = process.argv[3];
  const version_number = process.argv[4];

  core.debug(
    `Running script for ${FEATURE_FILE_PATH} on branch ${branch_name}`
  );

  const XRAY_TOKEN = await getXrayToken(CLIENT_ID, CLIENT_SECRET);

  const responseData = await uploadFeatureFileToXray(
    FEATURE_FILE_PATH,
    XRAY_TOKEN
  );
  if (!responseData) {
    return;
  }

  const targetVersions =
    branch_name === "develop"
      ? getTargetVersion(version_number).concat("Cloud")
      : getTargetVersion(version_number);

  const testSelfs = responseData.updatedOrCreatedTests?.map(
    (test) => test.self
  );

  // Updating the Issues to match the target version and components
  // For now we have only centreon-web
  await updateJiraIssues(testSelfs, targetVersions, ["centreon-web"]);

  const testSetKey = await extractDataFromFeatureFile(FEATURE_FILE_PATH);
  if (!testSetKey) {
    core.warning("No test set mentioned");
    return;
  }

  core.debug("Adding tests to the testSet: ", testSetKey);

  const testSetId = await getJiraIssueId(testSetKey);
  if (!testSetId) {
    core.error(`No test set found having this key: ${testSetKey}`);
    return;
  }

  const testIds = responseData.updatedOrCreatedTests?.map((test) => test.id);
  const variables = {
    testSetId: testSetId,
    testIds: testIds,
  };

  const headers = {
    Authorization: `Bearer ${XRAY_TOKEN}`,
    "Content-Type": "application/json",
  };

  const graphql_request = {
    query: `
          mutation AddTestsToTestSet ($testSetId: String!, $testIds: [String!]!) {
            addTestsToTestSet(
                issueId: $testSetId,
                testIssueIds: $testIds
            ) {
                addedTests
                warning
              }
            }
          `,
    variables: variables,
  };

  await axios
    .post(GRAPHQL_URL, graphql_request, {
      headers: headers,
    })
    .then((response) => {
      if (response.status !== 200) {
        core.error(
          `GraphQL Request Failed with Status Code: ${response.status}`
        );
        core.debug("Error Data: ", JSON.stringify(response.data, null, 2));
        return;
      }

      core.debug("Response Data: ", JSON.stringify(response.data, null, 2));
    })
    .catch((error) => {
      core.error(`GraphQL Request Failed: ${error}`);
    });
}

if (require.main === module) {
  main();
}
