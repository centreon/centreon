export const activityLogsEndpoint = '/administration/activity-logs';

export const actorsEndpoint = `${activityLogsEndpoint}/actors`;

export const tokensEndpoint = `${activityLogsEndpoint}/tokens`;

export const getChangesEndpoint = (id: number): string =>
  `${activityLogsEndpoint}/${id}/changes`;
