import React from 'react';

import { renderHook } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { LocalizationProvider } from '@mui/x-date-pickers';

import { useLocaleDateTimeFormat } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { CustomTimePeriodProperty } from './Details/tabs/Graph/models';
import useDateTimePickerAdapter from './useDateTimePickerAdapter';
import DateTimePickerInput from './Graph/Performance/TimePeriods/DateTimePickerInput';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

const numberDaysInWeek = 7;

const days = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday'
];

const months2023 = [
  {
    January: {
      date: '2023-01-01T12:59:41.041Z',
      firstDay: { day: days[0], indexDayInRowWeek: 0, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 31 },
      numberWeeks: 5
    }
  },
  {
    February: {
      date: '2023-02-01T12:59:41.041Z',
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 28 },
      numberWeeks: 5
    }
  },
  {
    March: {
      date: '2023-03-01T12:59:41.041Z',
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[5], indexDayInRowWeek: 5, value: 31 },
      numberWeeks: 5
    }
  },
  {
    April: {
      date: '2023-04-01T12:59:41.041Z',
      firstDay: { day: days[6], indexDayInRowWeek: 6, value: 1 },
      lastDay: { day: days[0], indexDayInRowWeek: 0, value: 30 },
      numberWeeks: 6
    }
  },
  {
    May: {
      date: '2023-05-01T12:59:41.041Z',
      firstDay: { day: days[1], indexDayInRowWeek: 1, value: 1 },
      lastDay: { day: days[3], indexDayInRowWeek: 3, value: 31 },
      numberWeeks: 5
    }
  },
  {
    June: {
      date: '2023-06-01T12:59:41.041Z',
      firstDay: { day: days[4], indexDayInRowWeek: 4, value: 1 },
      lastDay: { day: days[5], indexDayInRowWeek: 5, value: 30 },
      numberWeeks: 5
    }
  },
  {
    July: {
      date: '2023-07-01T12:59:41.041Z',
      firstDay: { day: days[6], indexDayInRowWeek: 6, value: 1 },
      lastDay: { day: days[1], indexDayInRowWeek: 1, value: 31 },
      numberWeeks: 6
    }
  },
  {
    August: {
      date: '2023-08-01T12:59:41.041Z',
      firstDay: { day: days[2], indexDayInRowWeek: 2, value: 1 },
      lastDay: { day: days[4], indexDayInRowWeek: 4, value: 31 },
      numberWeeks: 5
    }
  },
  {
    September: {
      date: '2023-09-01T12:59:41.041Z',
      firstDay: { day: days[5], indexDayInRowWeek: 5, value: 1 },
      lastDay: { day: days[6], indexDayInRowWeek: 6, value: 30 },
      numberWeeks: 5
    }
  },
  {
    October: {
      date: '2023-10-01T12:59:41.041Z',
      firstDay: { day: days[0], indexDayInRowWeek: 0, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 31 },
      numberWeeks: 5
    }
  },
  {
    November: {
      date: '2023-11-01T12:59:41.041Z',
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[4], indexDayInRowWeek: 4, value: 30 },
      numberWeeks: 5
    }
  },
  {
    December: {
      date: '2023-12-01T12:59:41.041Z',
      firstDay: { day: days[5], indexDayInRowWeek: 5, value: 1 },
      lastDay: { day: days[0], indexDayInRowWeek: 0, value: 31 },
      numberWeeks: 6
    }
  }
];

const month2023Reverse = [
  {
    December: {
      date: '2023-12-01T12:59:41.041Z',
      firstDay: { day: days[5], indexDayInRowWeek: 5, value: 1 },
      lastDay: { day: days[0], indexDayInRowWeek: 0, value: 31 },
      numberWeeks: 6
    }
  },
  {
    November: {
      date: '2023-11-01T12:59:41.041Z',
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[4], indexDayInRowWeek: 4, value: 30 },
      numberWeeks: 5
    }
  },
  {
    October: {
      date: '2023-10-01T12:59:41.041Z',
      firstDay: { day: days[0], indexDayInRowWeek: 0, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 31 },
      numberWeeks: 5
    }
  },
  {
    September: {
      date: '2023-09-01T12:59:41.041Z',
      firstDay: { day: days[5], indexDayInRowWeek: 5, value: 1 },
      lastDay: { day: days[6], indexDayInRowWeek: 6, value: 30 },
      numberWeeks: 5
    }
  },
  {
    August: {
      date: '2023-08-01T12:59:41.041Z',
      firstDay: { day: days[2], indexDayInRowWeek: 2, value: 1 },
      lastDay: { day: days[4], indexDayInRowWeek: 4, value: 31 },
      numberWeeks: 5
    }
  },
  {
    July: {
      date: '2023-07-01T12:59:41.041Z',
      firstDay: { day: days[6], indexDayInRowWeek: 6, value: 1 },
      lastDay: { day: days[1], indexDayInRowWeek: 1, value: 31 },
      numberWeeks: 6
    }
  },
  {
    June: {
      date: '2023-06-01T12:59:41.041Z',
      firstDay: { day: days[4], indexDayInRowWeek: 4, value: 1 },
      lastDay: { day: days[5], indexDayInRowWeek: 5, value: 30 },
      numberWeeks: 5
    }
  },
  {
    May: {
      date: '2023-05-01T12:59:41.041Z',
      firstDay: { day: days[1], indexDayInRowWeek: 1, value: 1 },
      lastDay: { day: days[3], indexDayInRowWeek: 3, value: 31 },
      numberWeeks: 5
    }
  },
  {
    April: {
      date: '2023-04-01T12:59:41.041Z',
      firstDay: { day: days[6], indexDayInRowWeek: 6, value: 1 },
      lastDay: { day: days[0], indexDayInRowWeek: 0, value: 30 },
      numberWeeks: 6
    }
  },
  {
    March: {
      date: '2023-03-01T12:59:41.041Z',
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[5], indexDayInRowWeek: 5, value: 31 },
      numberWeeks: 5
    }
  },
  {
    February: {
      date: '2023-02-01T12:59:41.041Z',
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 28 },
      numberWeeks: 5
    }
  },
  {
    January: {
      date: '2023-01-01T12:59:41.041Z',
      firstDay: { day: days[0], indexDayInRowWeek: 0, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 31 },
      numberWeeks: 5
    }
  }
];

const testData = [
  {
    button: 'Next month',
    data: months2023,
    initialDate: '2023-01-01T12:59:41.041Z',
    timezone: 'Europe/Paris'
  },
  {
    button: 'Previous month',
    data: month2023Reverse,
    initialDate: '2023-12-01T12:59:41.041Z',
    timezone: 'Europe/Paris'
  }
];

enum ButtonCalendar {
  NMONTH = 'Next month',
  PMONTH = 'Previous month'
}
interface GetPreviousNextMonth {
  currentMonth: string;
  labelButton: string;
}

const getNextMonth = ({
  currentMonth,
  labelButton
}: GetPreviousNextMonth): Cypress.Chainable | null => {
  if (equals(currentMonth, 'December')) {
    return null;
  }

  return cy.get(`[aria-label="${labelButton}"]`).click();
};

const getPreviousMonth = ({
  currentMonth,
  labelButton
}: GetPreviousNextMonth): Cypress.Chainable | null => {
  if (equals(currentMonth, 'January')) {
    return null;
  }

  return cy.get(`[aria-label="${labelButton}"]`).click();
};

const changeDate = (): void => undefined;

const setStart = undefined;
const checkIfDuplicateExists = (arr: Array<unknown>): boolean => {
  return new Set(arr).size !== arr.length;
};

testData.forEach((item) =>
  describe(`DateTimePicker`, () => {
    before(() => {
      const userData = renderHook(() => useAtomValue(userAtom));

      userData.result.current.timezone = item.timezone;
      userData.result.current.locale = 'en_US';
    });

    beforeEach(() => {
      const { result } = renderHook(() => useDateTimePickerAdapter());

      const { Adapter } = result.current;
      cy.mount({
        Component: (
          <LocalizationProvider adapterLocale="en" dateAdapter={Adapter}>
            <DateTimePickerInput
              changeDate={changeDate}
              date={new Date(item.initialDate)}
              property={CustomTimePeriodProperty.start}
              setDate={setStart}
            />
          </LocalizationProvider>
        )
      });
    });

    it('checks input calendar value contains correct date', () => {
      const { result } = renderHook(() => useLocaleDateTimeFormat());
      const { format } = result.current;

      const dateInput = format({
        date: dayjs(item.initialDate).tz(item.timezone),
        formatString: 'L hh:mm A'
      });

      cy.get('input').should('have.value', dateInput);
    });

    it(`displays the correct number of days for the current month when the ${item.button} button is clicked`, () => {
      cy.get('input').click();
      item.data.forEach((element) => {
        const { lastDay } = Object.values(element)[0];

        cy.get('[role="rowgroup"]').children().as('listWeeks');
        cy.get('@listWeeks').children('button').as('days');
        cy.get('@days').should('have.length', lastDay.value);

        const currentMonth = Object.keys(element)[0];

        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });

    it(`does not duplicate days in any of the month in the year when the ${item.button} button is clicked`, () => {
      cy.get('input').click();
      item.data.forEach((element) => {
        const daysInCurrentMonth: Array<string> = [];
        cy.get('[role="rowgroup"]').first().children().as('listWeeks');
        cy.get('@listWeeks').children('button').as('days');

        cy.get('@days')
          .each(($day) => daysInCurrentMonth.push($day.text()))
          .as('currentDays');

        cy.get('@currentDays').then(() => {
          const isDuplicateExist = checkIfDuplicateExists(daysInCurrentMonth);

          return expect(isDuplicateExist).to.be.false;
        });

        const currentMonth = Object.keys(element)[0];
        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });

    it(`displays the first day as the start of the week when the ${item.button} button is clicked`, () => {
      cy.get('input').click();

      item.data.forEach((element) => {
        const { firstDay, numberWeeks } = Object.values(element)[0];

        cy.get('[role="rowgroup"]').children().as('listWeeks');

        cy.get('@listWeeks').should('have.length', numberWeeks);
        cy.get('@listWeeks').eq(0).as('firstWeek');
        cy.get('@firstWeek').children().as('listDaysInFirstWeek');

        cy.get('@listDaysInFirstWeek').should('have.length', numberDaysInWeek);
        cy.get('@listDaysInFirstWeek')
          .eq(firstDay.indexDayInRowWeek)
          .as('firstDayInWeek');
        cy.get('@firstDayInWeek').contains(firstDay.value);

        const currentMonth = Object.keys(element)[0];
        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });

    it(`displays the last day as the end of the week when the ${item.button} button is clicked`, () => {
      cy.get('input').click();

      item.data.forEach((element) => {
        const { lastDay, numberWeeks } = Object.values(element)[0];

        cy.get('[role="rowgroup"]').children().as('listWeeks');

        cy.get('@listWeeks').should('have.length', numberWeeks);

        cy.get('@listWeeks')
          .eq(numberWeeks - 1)
          .as('lastWeek');

        cy.get('@lastWeek').children().as('listDaysInLastWeek');
        cy.get('@listDaysInLastWeek').should('have.length', numberDaysInWeek);

        cy.get('@listDaysInLastWeek')
          .eq(lastDay.indexDayInRowWeek)
          .as('lastDayInWeek');
        cy.get('@lastDayInWeek').contains(lastDay.value);
        const currentMonth = Object.keys(element)[0];
        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });

    it(`displays the month and the year in the calendar's header when the ${item.button} button is clicked`, () => {
      cy.get('input').click();
      const { result } = renderHook(() => useLocaleDateTimeFormat());

      item.data.forEach((element) => {
        const { date } = Object.values(element)[0];

        const { format } = result.current;
        const monthAndYear = format({
          date: dayjs(date).tz(item.timezone),
          formatString: 'MMMM YYYY'
        });
        cy.contains(monthAndYear);
        const currentMonth = Object.keys(element)[0];
        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });

    it(`displays the correct day name on calendar's header when the ${item.button} button is clicked`, () => {
      cy.get('input').click();

      const { result } = renderHook(() => useLocaleDateTimeFormat());

      item.data.forEach((element) => {
        const { date } = Object.values(element)[0];
        const dateByTimeZone = dayjs(date).tz(item.timezone);
        const firstDay = dateByTimeZone.isUTC()
          ? dateByTimeZone.utc().startOf('month').startOf('week')
          : dateByTimeZone.startOf('month').startOf('week');

        const { format } = result.current;
        const daysArray = [0, 1, 2, 3, 4, 5, 6].map((diff) =>
          format({
            date: firstDay.add(diff, 'day'),
            formatString: 'dd'
          })
        );
        daysArray.forEach((day) => cy.contains(day.toUpperCase()));
        const currentMonth = Object.keys(element)[0];
        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });

    it(`displays the calendar for the timezone ${item.timezone} when the ${item.button} button is clicked`, () => {
      cy.get('input').click();

      item.data.forEach((element) => {
        cy.matchImageSnapshot(
          `calendar-${item.timezone}-${
            Object.keys(element)[0]
          }-when-clicking-on-${item.button}`
        );

        const currentMonth = Object.keys(element)[0];
        if (equals(item.button, ButtonCalendar.PMONTH)) {
          getPreviousMonth({ currentMonth, labelButton: item.button });
        } else {
          getNextMonth({ currentMonth, labelButton: item.button });
        }
      });
    });
  })
);
