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

export interface PollersIssuesList {
  issues: {
    database?: Alert;
    latency?: Alert;
    stability?: Alert;
  };
  refreshTime: number;
  total: number;
}
