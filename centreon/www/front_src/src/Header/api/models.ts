export interface AlertDetails {
  poller: {
    id: number;
    name: string;
    since: number | string;
  };
  total: number;
}

export interface Alert {
  critical?: AlertDetails;
  total: number;
  warning?: AlertDetails;
}

// api inconsistency return an empty array when there is no issues
// but decoders don't allow empty array as a valid type,
// the real signature would be :
// issues: {
//   database?: Alert;
//   latency?: Alert;
//   stability?: Alert;
// } | [];

export interface NonNullIssues {
  database?: Alert;
  latency?: Alert;
  stability?: Alert;
}
export interface PollersIssuesList {
  issues: NonNullIssues | Array<string>;
  refreshTime: number;
  total: number;
}
