import type { ClapiAction } from '../helpers/CentreonApi';

/**
 * Fixtures for the legacy Time period configuration migration.
 *
 * The Cypress feature creates a time period entirely through the legacy PHP
 * form (`main.php?p=60304`). For Playwright we keep the *creation* under test
 * in the UI, but express the matching CLAPI definition so the test can be
 * cleaned up idempotently (CLAPI `TP DEL`) regardless of whether the UI save
 * succeeded.
 *
 * Time range and exception values mirror the Cypress `setTimePeriod` helper in
 * `features/Time-Period/common.ts`.
 */

export interface TimePeriodDay {
  /** Day column name on the legacy form (`tp_sunday`, `tp_monday`, ...). */
  day:
    | 'sunday'
    | 'monday'
    | 'tuesday'
    | 'wednesday'
    | 'thursday'
    | 'friday'
    | 'saturday';
  /** Time range expression, e.g. `07:00-12:00,13:00-18:00`. */
  timeRange: string;
}

export interface TimePeriodException {
  /** Exception date expression, e.g. `december 25` or `august 1 - 31`. */
  date: string;
  /** Time range expression for that exception, e.g. `00:00-24:00`. */
  timeRange: string;
}

export interface TimePeriodSeed {
  name: string;
  alias: string;
  days: Array<TimePeriodDay>;
  exceptions: Array<TimePeriodException>;
}

/**
 * Holiday-style time period: every weekday filled, plus four single-day
 * exceptions (1 Jan, 25 May, 14 July, 25 December). Mirrors `setTimePeriod`.
 */
export const holidaysTimePeriod = (name: string): TimePeriodSeed => ({
  alias: 'pw-time-period-alias',
  days: [
    { day: 'sunday', timeRange: '14:00-16:00' },
    { day: 'monday', timeRange: '07:00-12:00,13:00-18:00' },
    { day: 'tuesday', timeRange: '07:00-18:00' },
    { day: 'wednesday', timeRange: '07:00-12:00,13:00-17:00' },
    { day: 'thursday', timeRange: '14:00-16:00' },
    { day: 'friday', timeRange: '07:00-18:00' },
    { day: 'saturday', timeRange: '10:00-16:00' }
  ],
  exceptions: [
    { date: 'december 25', timeRange: '00:00-22:59,23:00-24:00' },
    { date: 'january 1', timeRange: '00:00-24:00' },
    { date: 'july 14', timeRange: '00:00-24:00' },
    { date: 'may 25', timeRange: '00:00-24:00' }
  ],
  name
});

/**
 * Time period excluding a whole month (1 - 31 August). Mirrors the Cypress
 * "range of dates to exclude" scenario.
 */
export const rangeExclusionTimePeriod = (name: string): TimePeriodSeed => ({
  alias: 'pw-time-period-alias',
  days: [
    { day: 'sunday', timeRange: '14:00-16:00' },
    { day: 'monday', timeRange: '07:00-12:00,13:00-18:00' },
    { day: 'tuesday', timeRange: '07:00-18:00' },
    { day: 'wednesday', timeRange: '07:00-12:00,13:00-17:00' },
    { day: 'thursday', timeRange: '14:00-16:00' },
    { day: 'friday', timeRange: '07:00-18:00' },
    { day: 'saturday', timeRange: '10:00-16:00' }
  ],
  exceptions: [{ date: 'august 1 - 31', timeRange: '00:00-24:00' }],
  name
});

/** CLAPI `TP DEL` action, used for best-effort cleanup of a created period. */
export const deleteTimePeriodActions = (name: string): Array<ClapiAction> => [
  { action: 'DEL', object: 'TP', values: name }
];
