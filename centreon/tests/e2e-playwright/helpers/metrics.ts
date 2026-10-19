import { expect } from '@playwright/test';

import { queryCentreonDb } from './docker';

/**
 * Wait for a service of the central server to have performance data: its
 * metrics exist once its first check is stored, and its curves need points.
 * Returns the number of metrics of the service.
 */
export const waitForServiceMetrics = async ({
  metrics = [],
  points = 1,
  service
}: {
  metrics?: Array<string>;
  points?: number;
  service: string;
}): Promise<number> => {
  const ofService = `FROM centreon_storage.index_data i JOIN centreon_storage.metrics m ON m.index_id = i.id WHERE i.host_name = 'Centreon-Server' AND i.service_description = '${service}'`;
  const count = (sql: string): number => Number(queryCentreonDb(sql).trim());

  await expect
    .poll(
      () =>
        count(
          `SELECT COUNT(*) ${ofService}${metrics.length ? ` AND m.metric_name IN (${metrics.map((name) => `'${name}'`).join(', ')})` : ''}`
        ) >= Math.max(metrics.length, 1) &&
        count(
          `SELECT COUNT(*) FROM centreon_storage.data_bin WHERE id_metric = (SELECT MIN(m.metric_id) ${ofService})`
        ) >= points,
      {
        intervals: [10_000],
        message: `performance data of ${service}`,
        timeout: 300_000
      }
    )
    .toBe(true);

  return count(`SELECT COUNT(*) ${ofService}`);
};
