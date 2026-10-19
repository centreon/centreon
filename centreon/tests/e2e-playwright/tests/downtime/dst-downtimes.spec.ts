import { randomUUID } from 'node:crypto';

import { type RestrictedUser, restrictedUser } from '../../fixtures/acl';
import { expect, test } from '../../fixtures/test';
import { execInWebContainer, queryCentreonDb } from '../../helpers/docker';
import {
  type DateParts,
  type DstCase,
  expectedSeconds,
  formDate,
  isoDay,
  realtimeCases,
  recurrentCases,
  scheduledWindowFor,
  timezone,
  transitionDay
} from '../../helpers/dst';
import { signIn } from '../../helpers/session';
import { DowntimesListingPage } from '../../pages/DowntimesListingPage';

/**
 * Replaces Downtime/01 to 03: downtimes entered in wall-clock time around the
 * next DST transitions of Europe/Paris get the start, end and duration derived
 * independently in `helpers/dst.ts`. Realtime downtimes go through the legacy
 * form; recurrent ones through the downtime cron, run under faketime just
 * before their window.
 */
const host = 'Centreon-Server';
let hostId: string;
let serviceId: string;
let service: string;

test.beforeAll(() => {
  [hostId, serviceId, service] = queryCentreonDb(
    `SELECT h.host_id, s.service_id, s.service_description FROM host h JOIN host_service_relation r ON r.host_host_id = h.host_id JOIN service s ON s.service_id = r.service_service_id WHERE h.host_name = '${host}' AND s.service_activate = '1' ORDER BY s.service_id LIMIT 1`
  )
    .trim()
    .split('\t');
});

const expectedWindow = ({ expected, transition }: DstCase) => {
  if (!expected) {
    return null;
  }
  const day = transitionDay(transition);

  return {
    end: expectedSeconds(day, expected.end, 'later'),
    start: expectedSeconds(day, expected.start, 'earlier')
  };
};

test.afterEach(async ({ adminApi }) => {
  await adminApi.cancelServiceDowntimes(Number(hostId), Number(serviceId));
});

test.describe('Realtime downtimes', () => {
  // The form script reads the times in the browser timezone: in Europe/Paris
  // it shifts an end time it finds inconsistent with a non-existent start.
  test.use({ timezoneId: 'UTC' });

  let user: RestrictedUser;

  test.beforeEach(async ({ adminApi, page }) => {
    user = restrictedUser({
      contactParams: { admin: '1', timezone },
      menus: [{ access: 'rw', topology: 'Monitoring;Downtimes' }],
      name: `pw-dst-${randomUUID().slice(0, 6)}`
    });
    await adminApi.provision(user.provisioning);
    await signIn(page, user.credentials);
  });

  test.afterEach(async ({ adminApi }) => {
    await adminApi.cleanUp(user.teardown);
  });

  for (const [name, dstCase] of Object.entries(realtimeCases)) {
    test(`schedules the ${name} case as expected`, async ({ page }) => {
      const day = transitionDay(dstCase.transition);
      await new DowntimesListingPage(page).schedule({
        end: {
          date: formDate(day, dstCase.end.dayOffset),
          time: dstCase.end.time
        },
        hostId: Number(hostId),
        serviceId: Number(serviceId),
        start: {
          date: formDate(day, dstCase.start.dayOffset),
          time: dstCase.start.time
        }
      });
      // The form comments the downtime with the user: the downtimes of the
      // other cases may not be cancelled yet.
      const scheduled = (from: number, to: number) =>
        Number(
          queryCentreonDb(
            `SELECT COUNT(*) FROM downtimes WHERE host_id = ${hostId} AND service_id = ${serviceId} AND cancelled = 0 AND start_time >= ${from} AND end_time <= ${to} AND comment_data LIKE '%${user.credentials.login}%'`,
            'centreon_storage'
          ).trim()
        );
      const expected = expectedWindow(dstCase);

      if (expected) {
        expect(expected.end - expected.start).toBe(
          dstCase.expected?.durationSeconds
        );
        await expect
          .poll(() => scheduled(expected.start, expected.end), {
            timeout: 60_000
          })
          .toBe(1);
      } else {
        const dayStart = expectedSeconds(day, { dayOffset: 0, time: '00:00' });
        expect(scheduled(dayStart, dayStart + 2 * 86_400)).toBe(0);
      }
    });
  }
});

test.describe('Recurrent downtimes', () => {
  const centcore = '/var/lib/centreon/centcore';
  let downtime: string;
  let hostTimezone: string;

  /** Run the downtime cron at a frozen time and return the commands it emits. */
  const runCron = (now: string): string =>
    // Removed right away so that they never reach the engine.
    execInWebContainer(
      `rm -f ${centcore}/*-downtimes; TZ='${timezone}' faketime '${now}' php /usr/share/centreon/cron/downtimeManager.php >/dev/null; cat ${centcore}/*-downtimes 2>/dev/null; rm -f ${centcore}/*-downtimes`
    );

  test.beforeEach(() => {
    downtime = `pw-dst-${randomUUID().slice(0, 6)}`;
    // The cron reads the windows in the timezone of the host.
    hostTimezone = queryCentreonDb(
      `SELECT IFNULL(host_location, 'NULL') FROM host WHERE host_id = ${hostId}`
    ).trim();
    queryCentreonDb(
      `UPDATE host SET host_location = (SELECT timezone_id FROM timezone WHERE timezone_name = '${timezone}') WHERE host_id = ${hostId}`
    );
  });

  test.afterEach(async ({ adminApi }) => {
    queryCentreonDb(
      `UPDATE host SET host_location = ${hostTimezone} WHERE host_id = ${hostId}`
    );
    await adminApi.cleanUp([
      { action: 'DEL', object: 'DOWNTIME', values: downtime }
    ]);
  });

  for (const [name, dstCase] of Object.entries(recurrentCases)) {
    test(`schedules the ${name} case as expected`, async ({ adminApi }) => {
      const { end, faketime, start, transition } = dstCase;
      if (!faketime) {
        throw new Error(`Recurrent case "${name}" has no faketime`);
      }
      await adminApi.provision([
        {
          action: 'ADD',
          object: 'DOWNTIME',
          values: `${downtime};${downtime}`
        },
        {
          action: 'ADDWEEKLYPERIOD',
          object: 'DOWNTIME',
          values: `${downtime};${start.time};${end.time};1;;1,2,3,4,5,6,7`
        },
        {
          action: 'ADDSERVICE',
          object: 'DOWNTIME',
          values: `${downtime};${host},${service}`
        }
      ]);
      const commands = runCron(
        `${isoDay(transitionDay(transition), faketime.dayOffset)} ${faketime.time}:00`
      );
      const scheduled = scheduledWindowFor(
        commands,
        `SCHEDULE_SVC_DOWNTIME;${host};${service};`
      );
      const expected = expectedWindow(dstCase);

      expect(scheduled).toEqual(expected);
      if (expected) {
        expect(expected.end - expected.start).toBe(
          dstCase.expected?.durationSeconds
        );
      }
    });
  }

  test('schedules a recurrent downtime on a host', async ({ adminApi }) => {
    await adminApi.provision([
      { action: 'ADD', object: 'DOWNTIME', values: `${downtime};${downtime}` },
      {
        action: 'ADDWEEKLYPERIOD',
        object: 'DOWNTIME',
        values: `${downtime};12:00;18:00;1;;1,2,3,4,5,6,7`
      },
      { action: 'ADDHOST', object: 'DOWNTIME', values: `${downtime};${host}` }
    ]);
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: timezone })
      .format(new Date())
      .split('-')
      .map(Number) as DateParts;
    // A year ahead: harmless should a command reach the engine.
    const commands = runCron(`${isoDay(today, 365)} 11:58:00`);

    expect(
      scheduledWindowFor(commands, `SCHEDULE_HOST_DOWNTIME;${host};`)
    ).toEqual({
      end: expectedSeconds(today, { dayOffset: 365, time: '18:00' }),
      start: expectedSeconds(today, { dayOffset: 365, time: '12:00' })
    });
  });
});
