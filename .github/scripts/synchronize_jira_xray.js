const axios = require("axios");
const fs = require("fs");
const FormData = require("form-data");

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
      console.log(`Authentication failed with status code: ${response.status}`);
      return;
    }

    const token = response.headers["x-access-token"];
    if (!token) {
      console.log(
        "Authentication failed. Token not found in the response headers."
      );
      return;
    }
    console.log("Authentication successful");
    return token;
  } catch (error) {
    console.log("Authentication failed");
    console.error(error);
  }
  return null;
}

function getTargetVersion(version_number) {
  const targetVersion = `OnPrem - ${version_number}`;
  console.log(`Target Versions: ${targetVersion}`);
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
    console.log(`Error reading feature file: ${error}`);
  }
  return null;
}

async function getJiraIssueId(testSetKey) {
  if (!testSetKey) {
    console.log("No Test Set Indicated");
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
      console.log(
        `Jira API Request Failed with Status Code: ${response.status}`
      );
      console.log(response.data);
      return;
    }

    console.log(`The ID of the testSet is: ${response.data.id}`);
    return response.data.id;
  } catch (error) {
    console.log(`Error fetching Jira issue: ${error}`);
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
      console.log(
        `Feature File Upload to Xray Failed with Status Code: ${response.status}`
      );
      console.log(response.data);
      return;
    }
    console.log("Feature file uploaded successfully to Xray.");
    console.log(response.data);
    return response.data;
  } catch (error) {
    console.log(`Error uploading feature file to Xray: ${error}`);
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
        console.log(
          `Jira Issue Update Failed with Status Code: ${response.status}`
        );
        console.log(response.data);
        return;
      }

      const existingIssueData = response.data;
      const existingCustomField10901 =
        existingIssueData.fields.customfield_10901 || [];
      const existingValues = existingCustomField10901
        ? existingCustomField10901.map((item) => item.value)
        : [];

      console.log("Existing values of version: ", existingValues);

      for (const targetVersion of targetVersions) {
        if (!existingValues.includes(targetVersion)) {
          existingValues.push(targetVersion);
        }
      }
      console.log("the new target versions are: ", existingValues);

      const issueUpdatePayload = {
        fields: {
          customfield_10901: existingValues.map((value) => ({
            value: value,
          })),
        },
      };
      console.log(
        `the issue update for ${api} is: `,
        JSON.stringify(issueUpdatePayload)
      );

      if (componentsList) {
        issueUpdatePayload.fields.components = componentsList.map(
          (component) => ({ name: component })
        );
      } else {
        console.log("No component mentioned");
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
        console.log(
          `Error updating issue ${api} in Jira. Status code: ${jira_response.status}`
        );
        console.log(jira_response.data);
        return;
      }

      console.log(`Issue ${api} updated successfully in Jira.`);
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
    console.log("No test set mentioned");
    return;
  }

  console.log("Adding tests to the testSet: ", testSetKey);

  const testSetId = await getJiraIssueId(testSetKey);
  if (!testSetId) {
    console.log(`No test set found having this key: ${testSetKey}`);
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
        console.log(
          `GraphQL Request Failed with Status Code: ${response.status}`
        );
        console.log("Error Data:");
        console.log(JSON.stringify(response.data, null, 2));
        return;
      }

      console.log("Response Data:");
      console.log(JSON.stringify(response.data, null, 2));
    })
    .catch((error) => {
      console.log(`GraphQL Request Failed: ${error}`);
    });
}

if (require.main === module) {
  main();
}
