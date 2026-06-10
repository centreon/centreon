/**
 * Shared helpers and fixtures for the Downtime e2e tests.
 *
 * DST (Daylight Saving Time) date math: computes the next spring-forward /
 * fall-back transition of a given IANA timezone and resolves wall-clock times
 * to absolute instants, so the *expected* downtime timestamps are derived
 * independently of Centreon (differential testing). Pure `Intl`, no extra dep.
 *
 * Europe/Paris transitions:
 *  - spring forward: clocks jump 02:00 -> 03:00 (02:00-03:00 does not exist, 23h day)
 *  - fall back:      clocks go 03:00 -> 02:00 (02:00-03:00 happens twice, 25h day)
 */

const timezone = 'Europe/Paris';

/** Central host carrying the passive service the downtimes are applied to. */
const centralHost = 'Centreon-Server';

interface WallInstant {
  /** Absolute instant (ms) of the wall-clock time, resolved in timezone. */
  ms: number;
  /** false when the wall-clock time does not exist (spring-forward gap). */
  exists: boolean;
}

interface WallPoint {
  dayOffset: number; // 0 = transition day
  time: string; // "HH:mm", "24:00" allowed for end-of-day
}

interface DstCase {
  transition: 'spring' | 'fall';
  start: WallPoint;
  end: WallPoint;
  // expected resolved window, or null when the downtime must NOT be scheduled
  expected: {
    start: WallPoint;
    end: WallPoint;
    durationSeconds: number;
  } | null;
}

type DateParts = [number, number, number];

/** UTC offset (minutes) of timezone at a given instant, machine-tz independent. */
const offsetMinutes = (date: Date): number => {
  const parts = new Intl.DateTimeFormat('en-US', {
    day: '2-digit',
    hour: '2-digit',
    hour12: false,
    minute: '2-digit',
    month: '2-digit',
    second: '2-digit',
    timeZone: timezone,
    year: 'numeric'
  })
    .formatToParts(date)
    .reduce(
      (acc, p) => ({ ...acc, [p.type]: p.value }),
      {} as Record<string, string>
    );

  const asUtc = Date.UTC(
    Number(parts.year),
    Number(parts.month) - 1,
    Number(parts.day),
    parts.hour === '24' ? 0 : Number(parts.hour),
    Number(parts.minute),
    Number(parts.second)
  );

  return (asUtc - date.getTime()) / 60000;
};

/** Next DST transition of the given kind ('spring' | 'fall') strictly after `from`. */
const nextTransition = (from: Date, kind: 'spring' | 'fall'): Date => {
  let previousOffset = offsetMinutes(from);
  let cursor = from.getTime();
  const oneHour = 3600e3;

  for (let i = 0; i < 24 * 420; i += 1) {
    cursor += oneHour;
    const offset = offsetMinutes(new Date(cursor));
    if (offset !== previousOffset) {
      const goesForward = offset > previousOffset;
      if ((kind === 'spring') === goesForward) {
        let low = cursor - oneHour;
        let high = cursor;
        while (high - low > 1000) {
          const mid = Math.floor((low + high) / 2);
          if (offsetMinutes(new Date(mid)) === previousOffset) low = mid;
          else high = mid;
        }

        return new Date(high);
      }
      previousOffset = offset;
    }
  }
  throw new Error(
    `No '${kind}' DST transition found within ~14 months after ${from.toISOString()}`
  );
};

/** YYYY-MM-DD of an instant, expressed in timezone. */
const isoDateInTz = (date: Date): string =>
  new Intl.DateTimeFormat('en-CA', { timeZone: timezone }).format(date);

/**
 * Resolve a wall-clock time (Y-M-D h:m, in timezone) to an absolute instant.
 * `exists` is false when the time falls into the spring-forward gap.
 */
const wallClockToInstant = (
  year: number,
  month: number,
  day: number,
  hour: number,
  minute: number
): WallInstant => {
  const naive = Date.UTC(year, month - 1, day, hour, minute);
  let guess = naive;
  for (let i = 0; i < 4; i += 1) {
    guess = naive - offsetMinutes(new Date(guess)) * 60000;
  }
  const back = new Intl.DateTimeFormat('en-US', {
    hour: '2-digit',
    hour12: false,
    minute: '2-digit',
    timeZone: timezone
  })
    .formatToParts(new Date(guess))
    .reduce(
      (acc, p) => ({ ...acc, [p.type]: p.value }),
      {} as Record<string, string>
    );

  const exists = Number(back.hour) === hour && Number(back.minute) === minute;

  return { exists, ms: guess };
};

// --- Test cases ------------------------------------------------------------

// DST contract enforced by centreonExternalCommand::getDowntimeTimestampFromDate
// (mirrors the original Behat scenarios).
const cases: Record<string, DstCase> = {
  fullFall: {
    end: { dayOffset: 0, time: '24:00' },
    expected: {
      durationSeconds: 90000, // 25h
      end: { dayOffset: 1, time: '00:00' },
      start: { dayOffset: 0, time: '00:00' }
    },
    start: { dayOffset: 0, time: '00:00' },
    transition: 'fall'
  },
  fullSpring: {
    end: { dayOffset: 0, time: '24:00' },
    expected: {
      durationSeconds: 82800, // 23h
      end: { dayOffset: 1, time: '00:00' },
      start: { dayOffset: 0, time: '00:00' }
    },
    start: { dayOffset: 0, time: '00:00' },
    transition: 'spring'
  },
  insideGap: {
    end: { dayOffset: 0, time: '02:33' },
    expected: null,
    start: { dayOffset: 0, time: '02:03' }, // whole window inside the non-existent hour
    transition: 'spring'
  },
  nonExistentStart: {
    end: { dayOffset: 0, time: '03:30' },
    expected: {
      durationSeconds: 1800, // 30m
      end: { dayOffset: 0, time: '03:30' },
      start: { dayOffset: 0, time: '03:00' }
    },
    start: { dayOffset: 0, time: '02:30' }, // does not exist -> clamped to 03:00
    transition: 'spring'
  }
};

// --- Form / assertion helpers ----------------------------------------------

const transitionDay = (kind: 'spring' | 'fall'): DateParts =>
  isoDateInTz(nextTransition(new Date(), kind))
    .split('-')
    .map(Number) as DateParts;

// MM/DD/YYYY expected by the legacy datepicker (CENTREON_LANG=en_US), for a day
// offset relative to the transition day.
const formDate = ([year, month, day]: DateParts, dayOffset: number): string => {
  const d = new Date(Date.UTC(year, month - 1, day + dayOffset));
  const mm = String(d.getUTCMonth() + 1).padStart(2, '0');
  const dd = String(d.getUTCDate()).padStart(2, '0');

  return `${mm}/${dd}/${d.getUTCFullYear()}`;
};

// Absolute instant (seconds) of an expected wall point on the transition day.
const expectedSeconds = (
  [year, month, day]: DateParts,
  point: WallPoint
): number => {
  const [hour, minute] = point.time.split(':').map(Number);

  return Math.floor(
    wallClockToInstant(year, month, day + point.dayOffset, hour, minute).ms /
      1000
  );
};

// --- SQL queries -----------------------------------------------------------

// First active service monitored on the given host (centreon database).
const monitoredServiceQuery = (hostName: string = centralHost): string =>
  `SELECT h.host_id, s.service_id FROM host h JOIN host_service_relation hsr ON hsr.host_host_id = h.host_id JOIN service s ON s.service_id = hsr.service_service_id WHERE h.host_name = '${hostName}' AND s.service_activate = '1' LIMIT 1`;

// Most recent non-cancelled downtime for a host/service (centreon_storage).
const latestDowntimeQuery = (hostId: number, serviceId: number): string =>
  `SELECT start_time, end_time FROM downtimes WHERE service_id = ${serviceId} AND host_id = ${hostId} AND cancelled = 0 ORDER BY downtime_id DESC LIMIT 1`;

// --- Engine log ------------------------------------------------------------

const engineLogPath = '/var/log/centreon-engine/centengine.log';

// Shell command (run via cy.execInContainer) emptying the engine log.
const truncateEngineLog = (): string => `truncate -s 0 ${engineLogPath}`;

// Shell command counting engine-log lines matching a pattern (0, no error,
// when none match).
const grepCountInEngineLog = (pattern: string): string =>
  `grep -c ${pattern} ${engineLogPath} || true`;

export {
  cases,
  centralHost,
  expectedSeconds,
  formDate,
  grepCountInEngineLog,
  latestDowntimeQuery,
  monitoredServiceQuery,
  transitionDay,
  truncateEngineLog
};
