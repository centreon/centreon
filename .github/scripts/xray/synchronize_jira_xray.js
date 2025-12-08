const axios = require("axios");
const fs = require("fs");
const FormData = require("form-data");
const core = require("@actions/core");

const XRAY_JIRA_USER_EMAIL = process.env.XRAY_JIRA_USER_EMAIL;
const XRAY_JIRA_TOKEN = process.env.XRAY_JIRA_TOKEN;
const XRAY_CLIENT_ID = process.env.XRAY_CLIENT_ID;
const XRAY_CLIENT_SECRET = process.env.XRAY_CLIENT_SECRET;

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
    core.info("Authentication successful");
    return token;
  } catch (error) {
    core.error(`Error Authentication: ${error}`);
  }
  return null;
}

function getTargetVersion(versionNumber) {
  const targetVersion = `OnPrem - ${versionNumber}`;
  core.info(`Target Versions: ${targetVersion}`);
  return [targetVersion];
}

async function extractDataFromFeatureFile(filePath) {
  try {
    const featureFileContent = fs.readFileSync(filePath, "utf-8");
    const match = featureFileContent.match(/#testSet:(.*?)$/ms);

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
          username: XRAY_JIRA_USER_EMAIL,
          password: XRAY_JIRA_TOKEN,
        },
      }
    );

    if (response.status !== 200) {
      core.error(
        `Jira API Request Failed with Status Code: ${response.status}`
      );
      core.info(`${response.data}`);
      return;
    }

    core.info(`The ID of the testSet is: ${response.data.id}`);
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
      core.info(`${response.data}`);
      return;
    }
    core.info("Feature file uploaded successfully to Xray.");
    core.info(`${JSON.stringify(response.data)}`);
    return response.data;
  } catch (error) {
    core.error(`Error uploading feature file to Xray: ${error}`);
  }

  return null;
}

async function postIssueStatus(api, statusPayload) {
  try {
    const response = await axios.post(api, statusPayload, {
      headers: {
        Accept: "application/json",
      },
      auth: {
        username: XRAY_JIRA_USER_EMAIL,
        password: XRAY_JIRA_TOKEN,
      },
    });

    if (response.status !== 204) {
      core.error(
        `Error updating issue's status ${api} in Jira of ${statusPayload.transition.id}. Status code: ${response.status}`
      );
      core.info(`${response.data}`);
      return false;
    }

    core.info(
      `Issue's status ${api} of ${statusPayload.transition.id} updated successfully in Jira.`
    );
    return true;
  } catch (error) {
    core.error(
      `Error updating issue status of ${statusPayload.transition.id}: ${error}`
    );
    return false;
  }
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
          username: XRAY_JIRA_USER_EMAIL,
          password: XRAY_JIRA_TOKEN,
        },
      });

      if (response.status !== 200) {
        core.error(
          `Jira Issue Update Failed with Status Code: ${response.status}`
        );
        core.info(`${response.data}`);
        return;
      }

      const existingIssueData = response.data;
      const existingCustomField10901 =
        existingIssueData.fields.customfield_10901 || [];
      const existingValues = existingCustomField10901
        ? existingCustomField10901.map((item) => item.value)
        : [];

      core.info(`Existing values of version: ${existingValues}`);

      for (const targetVersion of targetVersions) {
        if (!existingValues.includes(targetVersion)) {
          existingValues.push(targetVersion);
        }
      }
      core.info(`The new target versions are: ${existingValues}`);

      const issueUpdatePayload = {
        fields: {
          customfield_10901: existingValues.map((value) => ({
            value: value,
          })),
        },
      };
      core.info(
        `The issue update for ${api} is: ${JSON.stringify(issueUpdatePayload)}`
      );

      if (componentsList) {
        issueUpdatePayload.fields.components = componentsList.map(
          (component) => ({ name: component })
        );
      } else {
        core.warning("No component mentioned");
      }

      const jiraResponse = await axios.put(api, issueUpdatePayload, {
        headers: {
          Accept: "application/json",
        },
        auth: {
          username: XRAY_JIRA_USER_EMAIL,
          password: XRAY_JIRA_TOKEN,
        },
      });

      if (jiraResponse.status !== 204) {
        core.error(
          `Error updating issue ${api} in Jira. Status code: ${jiraResponse.status}`
        );
        core.info(`${jiraResponse.data}`);
        return;
      }

      core.info(`Issue ${api} updated successfully in Jira.`);

      // Update status of the test
      const statusAPI = `https://centreon.atlassian.net/rest/api/2/issue/${existingIssueData.key}/transitions?expand=transitions.fields`;

      const issueStatusPayloadToReadyForImplementation = {
        transition: { id: "61" },
      };
      if (
        !(await postIssueStatus(
          statusAPI,
          issueStatusPayloadToReadyForImplementation
        ))
      ) {
        return;
      }

      const issueStatusPayloadToStart = { transition: { id: "81" } };
      if (!(await postIssueStatus(statusAPI, issueStatusPayloadToStart))) {
        return;
      }

      const issueStatusPayloadToReadyForReview = { transition: { id: "21" } };
      if (
        !(await postIssueStatus(statusAPI, issueStatusPayloadToReadyForReview))
      ) {
        return;
      }

      const issueStatusPayloadToResolved = { transition: { id: "31" } };
      if (!(await postIssueStatus(statusAPI, issueStatusPayloadToResolved))) {
        return;
      }

      core.info(`Issue's status ${api} full updated successfully in Jira.`);
    } catch (error) {
      core.error(`Error full updating Jira issue: ${error}`);
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
  const branchName = process.argv[3];
  const versionNumber = process.argv[4];

  core.info(`Running script for ${FEATURE_FILE_PATH} on branch ${branchName}`);

  const XRAY_TOKEN = await getXrayToken(XRAY_CLIENT_ID, XRAY_CLIENT_SECRET);

  const responseData = await uploadFeatureFileToXray(
    FEATURE_FILE_PATH,
    XRAY_TOKEN
  );
  if (!responseData) {
    return;
  }

  const targetVersions =
    branchName === "develop"
      ? getTargetVersion(versionNumber).concat("Cloud")
      : getTargetVersion(versionNumber);

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

  core.info(`Adding tests to the testSet: ${testSetKey}`);

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

  const graphqlRequest = {
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
    .post(GRAPHQL_URL, graphqlRequest, {
      headers: headers,
    })
    .then((response) => {
      if (response.status !== 200) {
        core.error(
          `GraphQL Request Failed with Status Code: ${response.status}`
        );
        core.info(`Error Data: ${JSON.stringify(response.data, null, 2)}`);
        return;
      }

      core.info(`Response Data: ${JSON.stringify(response.data, null, 2)}`);
    })
    .catch((error) => {
      core.error(`GraphQL Request Failed: ${error}`);
    });
}

if (require.main === module) {
  main();
}
