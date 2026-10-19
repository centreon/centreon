import { randomUUID } from 'node:crypto';

import { expect, test } from '../../fixtures/test';
import { queryCentreonDb } from '../../helpers/docker';

/**
 * Replaces Downtime/04: the engine starts a fixed downtime at its start time
 * and ends it at its end time. Its flexible scenarios were ignored in Cypress.
 */
const host = 'Centreon-Server';

// The API takes `Y-m-d\TH:i:sP`, without milliseconds.
const isoSeconds = (epoch: number): string =>
  new Date(epoch * 1000).toISOString().replace(/\.\d{3}Z$/, '+00:00');

test('starts and ends a fixed downtime on time', async ({ adminApi }) => {
  test.setTimeout(240_000);
  const [hostId, serviceId] = queryCentreonDb(
    `SELECT h.host_id, s.service_id FROM host h JOIN host_service_relation r ON r.host_host_id = h.host_id JOIN service s ON s.service_id = r.service_service_id WHERE h.host_name = '${host}' AND s.service_activate = '1' ORDER BY s.service_id LIMIT 1`
  )
    .trim()
    .split('\t')
    .map(Number);
  const comment = `pw-downtime-${randomUUID().slice(0, 6)}`;
  const start = Math.floor(Date.now() / 1000) + 30;
  const end = start + 60;

  const lifecycle = () => {
    const [actualStart = 'NULL', actualEnd = 'NULL'] = queryCentreonDb(
      `SELECT IFNULL(actual_start_time, 'NULL'), IFNULL(actual_end_time, 'NULL') FROM downtimes WHERE host_id = ${hostId} AND service_id = ${serviceId} AND comment_data = '${comment}' ORDER BY downtime_id DESC LIMIT 1`,
      'centreon_storage'
    )
      .trim()
      .split('\t');

    return {
      actualEnd: actualEnd === 'NULL' ? null : Number(actualEnd),
      actualStart: actualStart === 'NULL' ? null : Number(actualStart)
    };
  };

  try {
    await adminApi.v2(
      'POST',
      `/monitoring/hosts/${hostId}/services/${serviceId}/downtimes`,
      {
        comment,
        duration: end - start,
        end_time: isoSeconds(end),
        is_fixed: true,
        start_time: isoSeconds(start)
      }
    );

    await test.step('It is not active before its start time', async () => {
      await expect
        .poll(() => lifecycle(), { timeout: 30_000 })
        .toEqual({
          actualEnd: null,
          actualStart: null
        });
    });

    await test.step('It becomes active at its start time', async () => {
      await expect
        .poll(() => lifecycle().actualStart, {
          intervals: [5_000],
          timeout: 90_000
        })
        .not.toBeNull();
      // The engine checks the downtimes every few seconds.
      expect(lifecycle().actualStart).toBeGreaterThanOrEqual(start);
      expect(lifecycle().actualStart).toBeLessThan(start + 30);
    });

    await test.step('It ends at its end time', async () => {
      await expect
        .poll(() => lifecycle().actualEnd, {
          intervals: [5_000],
          timeout: 120_000
        })
        .not.toBeNull();
      expect(lifecycle().actualEnd).toBeGreaterThanOrEqual(end);
      expect(lifecycle().actualEnd).toBeLessThan(end + 30);
    });
  } finally {
    await adminApi.cancelServiceDowntimes(hostId, serviceId);
  }
});
