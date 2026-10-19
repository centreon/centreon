import { expect } from '@playwright/test';

import { adminUser } from '../fixtures/credentials';
import { CentreonApi } from './CentreonApi';
import { queryCentreonDb } from './docker';

const baseURL =
  process.env.CENTREON_BASE_URL ?? 'http://localhost:4000/centreon';

/**
 * Wait for a service of the central server to have performance data: its
 * metrics exist once its first check is stored, and its curves need points.
 * Checks are forced meanwhile, the service only runs every few minutes.
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
  const [hostId, serviceId] = queryCentreonDb(
    `SELECT h.host_id, s.service_id FROM host h JOIN host_service_relation r ON r.host_host_id = h.host_id JOIN service s ON s.service_id = r.service_service_id WHERE h.host_name = 'Centreon-Server' AND s.service_description = '${service}'`
  )
    .trim()
    .split('\t')
    .map(Number);
  const ready = (): boolean =>
    count(
      `SELECT COUNT(*) ${ofService}${metrics.length ? ` AND m.metric_name IN (${metrics.map((name) => `'${name}'`).join(', ')})` : ''}`
    ) >= Math.max(metrics.length, 1) &&
    count(
      `SELECT COUNT(*) FROM centreon_storage.data_bin WHERE id_metric = (SELECT MIN(m.metric_id) ${ofService})`
    ) >= points;

  const api = await CentreonApi.create(baseURL);
  try {
    await api.login(adminUser);
    await expect
      .poll(
        async () => {
          if (ready()) {
            return true;
          }
          await api.v2('POST', '/monitoring/resources/check', {
            check: { is_forced: true },
            resources: [
              { id: serviceId, parent: { id: hostId }, type: 'service' }
            ]
          });

          return false;
        },
        {
          intervals: [10_000],
          message: `performance data of ${service}`,
          timeout: 300_000
        }
      )
      .toBe(true);
  } finally {
    await api.dispose();
  }

  return count(`SELECT COUNT(*) ${ofService}`);
};
