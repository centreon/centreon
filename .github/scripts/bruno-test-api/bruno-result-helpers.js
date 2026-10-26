export function failingChecks(requestResult) {
  return [
    ...(requestResult.assertionResults || []),
    ...(requestResult.testResults || []),
    ...(requestResult.preRequestTestResults || []),
    ...(requestResult.postResponseTestResults || []),
  ].filter((check) => check.status === "fail");
}

export function requestFailed(requestResult, failedChecks) {
  if (requestResult.status === "skipped") return failedChecks.length > 0;
  return requestResult.status !== "pass" || failedChecks.length > 0;
}
