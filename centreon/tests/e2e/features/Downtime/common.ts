/**
 * Shared helpers and fixtures for the Downtime e2e tests.
 *
 * DST (Daylight Saving Time) date math: computes the next spring-forward /
 * fall-back transition of a given IANA timezone and resolves local clock times
 * (the "HH:mm" you read on a wall clock) to absolute instants, so the *expected*
 * downtime timestamps are derived independently of Centreon (differential
 * testing). Pure `Intl`, no extra dep.
 *
 * Europe/Paris transitions:
 *  - spring forward: clocks jump 02:00 -> 03:00 (02:00-03:00 does not exist, 23h day)
 *  - fall back:      clocks go 03:00 -> 02:00 (02:00-03:00 happens twice, 25h day)
 */

const timezone = 'Europe/Paris';

/** Central host carrying the passive service the downtimes are applied to. */
const centralHost = 'Centreon-Server';

/** A local clock time entered on a given day, e.g. "02:30" on the transition day. */
interface ClockTime {
  dayOffset: number; // 0 = transition day
  time: string; // "HH:mm", "24:00" allowed for end-of-day
}

/** The absolute instant a ClockTime resolves to, in timezone. */
interface ResolvedInstant {
  /** Absolute instant (ms) of the clock time, resolved in timezone. */
  ms: number;
  /** false when the clock time does not exist (spring-forward gap). */
  exists: boolean;
}

interface DstCase {
  transition: 'spring' | 'fall';
  start: ClockTime;
  end: ClockTime;
  // expected resolved window, or null when the downtime must NOT be scheduled
  expected: {
    start: ClockTime;
    end: ClockTime;
    durationSeconds: number;
  } | null;
  // Recurrent only: wall clock to freeze (faketime) just before the cron picks
  // the downtime up. Unused by realtime cases (the form schedules directly).
  faketime?: ClockTime;
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
 * Resolve a local clock time (Y-M-D h:m, in timezone) to an absolute instant.
 *
 * A wall-clock time can map to zero, one, or two absolute instants:
 *  - zero -> spring-forward gap (the time does not exist); `exists` is false.
 *  - one  -> the normal case.
 *  - two  -> fall-back repeated hour; `prefer` picks which one: 'earlier' (first
 *            occurrence, larger UTC offset) or 'later' (second, smaller offset).
 * The engine opens a downtime on the earlier occurrence and closes it on the
 * later one, so resolve a start with 'earlier' and an end with 'later'.
 */
const clockTimeToInstant = (
  year: number,
  month: number,
  day: number,
  hour: number,
  minute: number,
  prefer: 'earlier' | 'later' = 'earlier'
): ResolvedInstant => {
  // "24:00" is end-of-day midnight: Date.UTC rolls it to the next day's 00:00,
  // so the resolved wall hour reads back as 0 — normalise the target the same way.
  const targetHour = hour % 24;
  const naive = Date.UTC(year, month - 1, day, hour, minute);

  // Candidate offsets: sampling a day before and after spans any nearby
  // transition, so both the pre- and post-transition offsets are considered.
  const offsets = new Set([
    offsetMinutes(new Date(naive - 24 * 3600e3)),
    offsetMinutes(new Date(naive + 24 * 3600e3))
  ]);

  const formatter = new Intl.DateTimeFormat('en-US', {
    hour: '2-digit',
    hour12: false,
    minute: '2-digit',
    timeZone: timezone
  });
  const formatsBackToTarget = (instant: number): boolean => {
    const parts = formatter
      .formatToParts(new Date(instant))
      .reduce(
        (acc, p) => ({ ...acc, [p.type]: p.value }),
        {} as Record<string, string>
      );
    // hour12:false formats midnight as "24" in some Intl engines (e.g. Chrome);
    // normalise it to 0 to match a 00:00 target.
    const backHour = parts.hour === '24' ? 0 : Number(parts.hour);

    return backHour === targetHour && Number(parts.minute) === minute;
  };

  // An instant is valid when applying its offset reproduces the target wall time.
  const valid = [...offsets]
    .map((offset) => naive - offset * 60000)
    .filter(formatsBackToTarget)
    .sort((a, b) => a - b);

  if (valid.length === 0) {
    return { exists: false, ms: naive };
  }

  // Earlier occurrence = smaller UTC instant (larger offset); later = the other.
  return {
    exists: true,
    ms: prefer === 'later' ? valid[valid.length - 1] : valid[0]
  };
};

// --- Test cases ------------------------------------------------------------

// Realtime downtimes scheduled directly from the form (MON-164065).
// DST contract enforced by centreonExternalCommand::getDowntimeTimestampFromDate.
const realtimeCases: Record<string, DstCase> = {
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

// Recurrent downtimes generated by the cron downtimeManager.php (MON-164066).
// Entered times use the transition day (offset 0); `faketime` freezes the clock
// a few minutes before the cron picks the downtime up (values from the legacy
// DowntimeDSTContext). Spring = 23h day, fall = repeated 02:00-03:00 hour.
const recurrentCases: Record<string, DstCase> = {
  fallEnd: {
    end: { dayOffset: 0, time: '02:30' }, // ends in the repeated hour -> later
    expected: {
      durationSeconds: 9000, // 2h30
      end: { dayOffset: 0, time: '02:30' },
      start: { dayOffset: 0, time: '01:00' }
    },
    faketime: { dayOffset: 0, time: '00:58' },
    start: { dayOffset: 0, time: '01:00' },
    transition: 'fall'
  },
  fallFullDay: {
    end: { dayOffset: 0, time: '24:00' },
    expected: {
      durationSeconds: 90000, // 25h
      end: { dayOffset: 1, time: '00:00' },
      start: { dayOffset: 0, time: '00:00' }
    },
    faketime: { dayOffset: -1, time: '23:58' },
    start: { dayOffset: 0, time: '00:00' },
    transition: 'fall'
  },
  fallInsideHour: {
    end: { dayOffset: 0, time: '02:33' }, // second occurrence -> later
    expected: {
      durationSeconds: 5400, // 1h30: first 02:03 (CEST) to second 02:33 (CET)
      end: { dayOffset: 0, time: '02:33' },
      start: { dayOffset: 0, time: '02:03' }
    },
    faketime: { dayOffset: 0, time: '01:58' },
    start: { dayOffset: 0, time: '02:03' }, // first occurrence -> earlier
    transition: 'fall'
  },
  fallNextDay: {
    end: { dayOffset: 1, time: '24:00' },
    expected: {
      durationSeconds: 86400, // 24h
      end: { dayOffset: 2, time: '00:00' },
      start: { dayOffset: 1, time: '00:00' }
    },
    faketime: { dayOffset: 0, time: '23:58' },
    start: { dayOffset: 1, time: '00:00' },
    transition: 'fall'
  },
  fallStart: {
    end: { dayOffset: 0, time: '03:33' },
    expected: {
      durationSeconds: 9000, // 2h30
      end: { dayOffset: 0, time: '03:33' },
      start: { dayOffset: 0, time: '02:03' }
    },
    faketime: { dayOffset: 0, time: '01:58' },
    start: { dayOffset: 0, time: '02:03' }, // starts in the repeated hour -> earlier
    transition: 'fall'
  },
  springEndClamped: {
    end: { dayOffset: 0, time: '02:30' }, // does not exist -> clamped to 03:00
    expected: {
      durationSeconds: 1800, // 30m
      end: { dayOffset: 0, time: '03:00' },
      start: { dayOffset: 0, time: '01:30' }
    },
    faketime: { dayOffset: 0, time: '01:26' },
    start: { dayOffset: 0, time: '01:30' },
    transition: 'spring'
  },
  springFullDay: {
    end: { dayOffset: 0, time: '24:00' },
    expected: {
      durationSeconds: 82800, // 23h
      end: { dayOffset: 1, time: '00:00' },
      start: { dayOffset: 0, time: '00:00' }
    },
    faketime: { dayOffset: -1, time: '23:56' },
    start: { dayOffset: 0, time: '00:00' },
    transition: 'spring'
  },
  springInsideGap: {
    end: { dayOffset: 0, time: '02:33' },
    expected: null, // whole window inside the non-existent hour -> not scheduled
    faketime: { dayOffset: 0, time: '01:58' },
    start: { dayOffset: 0, time: '02:03' },
    transition: 'spring'
  },
  springNextDay: {
    end: { dayOffset: 1, time: '24:00' },
    expected: {
      durationSeconds: 86400, // 24h
      end: { dayOffset: 2, time: '00:00' },
      start: { dayOffset: 1, time: '00:00' }
    },
    faketime: { dayOffset: 0, time: '23:58' },
    start: { dayOffset: 1, time: '00:00' },
    transition: 'spring'
  },
  springStartClamped: {
    end: { dayOffset: 0, time: '03:30' },
    expected: {
      durationSeconds: 1800, // 30m
      end: { dayOffset: 0, time: '03:30' },
      start: { dayOffset: 0, time: '03:00' }
    },
    faketime: { dayOffset: 0, time: '01:56' },
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

// YYYY-MM-DD for a day offset relative to the transition day (faketime input).
const isoDay = ([year, month, day]: DateParts, dayOffset: number): string =>
  new Date(Date.UTC(year, month - 1, day + dayOffset))
    .toISOString()
    .slice(0, 10);

// Absolute instant (seconds) of an expected clock time on the transition day.
// `prefer` disambiguates a fall-back repeated hour: 'earlier' for a start,
// 'later' for an end (matching how the engine opens/closes the window).
const expectedSeconds = (
  [year, month, day]: DateParts,
  point: ClockTime,
  prefer: 'earlier' | 'later' = 'earlier'
): number => {
  const [hour, minute] = point.time.split(':').map(Number);

  return Math.floor(
    clockTimeToInstant(year, month, day + point.dayOffset, hour, minute, prefer)
      .ms / 1000
  );
};

// --- SQL queries -----------------------------------------------------------

// First active service monitored on the given host (centreon database).
// Returns ids (form/DB lookups) plus names (select2 label and engine-log match).
const monitoredServiceQuery = (hostName: string = centralHost): string =>
  `SELECT h.host_id, h.host_name, s.service_id, s.service_description FROM host h JOIN host_service_relation hsr ON hsr.host_host_id = h.host_id JOIN service s ON s.service_id = hsr.service_service_id WHERE h.host_name = '${hostName}' AND s.service_activate = '1' LIMIT 1`;

// Most recent non-cancelled downtime for a host/service (centreon_storage).
const latestDowntimeQuery = (hostId: number, serviceId: number): string =>
  `SELECT start_time, end_time FROM downtimes WHERE service_id = ${serviceId} AND host_id = ${hostId} AND cancelled = 0 ORDER BY downtime_id DESC LIMIT 1`;

// Lifecycle of the latest non-cancelled downtime for a host/service
// (centreon_storage): the engine sets actual_start_time when the downtime
// becomes active and actual_end_time when it ends.
const downtimeLifecycleQuery = (hostId: number, serviceId: number): string =>
  `SELECT actual_start_time, actual_end_time, start_time, end_time FROM downtimes WHERE host_id = ${hostId} AND service_id = ${serviceId} AND cancelled = 0 ORDER BY downtime_id DESC LIMIT 1`;

// --- Recurrent downtime cron output ----------------------------------------

// The recurrent-downtime cron writes its emitted external commands into the
// centcore directory. Read them back to assert what was scheduled, decoupled
// from the gorgone -> engine delivery pipeline.
const readEmittedDowntimeCommands = (): Cypress.Chainable<string> =>
  cy
    .execInContainer({
      command: 'cat /var/lib/centreon/centcore/*-downtimes 2>/dev/null || true',
      name: 'web'
    })
    .then((result) =>
      typeof result === 'string' ? result : (result?.output ?? '')
    );

// Parse the start/end timestamps of the first emitted command matching `marker`
// (e.g. "SCHEDULE_SVC_DOWNTIME;host;svc;" or "SCHEDULE_HOST_DOWNTIME;host;").
// Returns null when no matching command was emitted.
const scheduledWindowFor = (
  commands: string,
  marker: string
): { start: number; end: number } | null => {
  // Keep the latest matching command: the cron may emit several over time and
  // the most recent one carries the window we just scheduled.
  const line = commands
    .split('\n')
    .filter((l) => l.includes(marker))
    .at(-1);
  if (!line) {
    return null;
  }
  const [start, end] = line
    .substring(line.indexOf(marker) + marker.length)
    .split(';');
  const parsedStart = Number(start);
  const parsedEnd = Number(end);
  if (!Number.isFinite(parsedStart) || !Number.isFinite(parsedEnd)) {
    return null;
  }

  return { end: parsedEnd, start: parsedStart };
};

export {
  centralHost,
  downtimeLifecycleQuery,
  expectedSeconds,
  formDate,
  isoDay,
  latestDowntimeQuery,
  monitoredServiceQuery,
  readEmittedDowntimeCommands,
  realtimeCases,
  recurrentCases,
  scheduledWindowFor,
  transitionDay
};
