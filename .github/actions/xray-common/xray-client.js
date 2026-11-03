// Shared helpers for the Node scripts of the xray-* actions: Xray Cloud endpoint,
// retry policy on transient HTTP failures, GraphQL client and .graphql file loading.

const fs = require("node:fs");
const path = require("node:path");

const XRAY_GRAPHQL_URL = "https://xray.cloud.getxray.app/api/v2/graphql";
const MAX_HTTP_RETRIES = 5;
const RETRY_DELAY_MS = 2000;

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

// Backoff before retrying a transient failure. `status` is null on a network error.
// Return the delay in ms, or null to stop retrying.
function retryDelayMs(attempt, status) {
  if (attempt >= MAX_HTTP_RETRIES) return null;
  const isTransient = status === null || status === 429 || status >= 500;
  if (!isTransient) return null;
  return RETRY_DELAY_MS;
}

// Fetch with retry/backoff on transient failures (policy in retryDelayMs).
async function fetchWithRetry(operation, label) {
  for (let attempt = 0; ; attempt++) {
    let response = null;
    let networkError = null;
    try {
      response = await operation();
      if (response.ok) return response;
    } catch (error) {
      networkError = error;
    }
    const delay = retryDelayMs(attempt, networkError ? null : response.status);
    if (delay == null) {
      if (networkError) throw networkError;
      return response;
    }
    console.log(`  ${label}: transient failure, retry #${attempt + 1} in ${delay}ms`);
    await sleep(delay);
  }
}

// Xray GraphQL caller bound to a token -> parsed JSON. Throws on a GraphQL-level error
// array. `retry: false` is for non-idempotent mutations: a 5xx may hide a write that
// actually happened, and replaying it would duplicate the entity.
function createXrayGraphqlClient(xrayToken) {
  return async function callXrayGraphql(graphqlQuery, queryVariables, { retry = true } = {}) {
    const request = () =>
      fetch(XRAY_GRAPHQL_URL, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${xrayToken}`,
        },
        body: JSON.stringify({ query: graphqlQuery, variables: queryVariables }),
      });
    const response = retry ? await fetchWithRetry(request, "Xray GraphQL") : await request();
    const responseJson = await response.json();
    if (responseJson.errors && responseJson.errors.length > 0) {
      throw new Error(`Xray GraphQL error: ${JSON.stringify(responseJson.errors)}`);
    }
    return responseJson;
  };
}

// One GraphQL operation per .graphql file, next to the calling script.
function loadGraphqlOperation(directory, operationName) {
  return fs.readFileSync(path.join(directory, `${operationName}.graphql`), "utf8");
}

module.exports = {
  XRAY_GRAPHQL_URL,
  fetchWithRetry,
  createXrayGraphqlClient,
  loadGraphqlOperation,
};
