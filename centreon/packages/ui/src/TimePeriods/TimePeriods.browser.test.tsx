import { useLocaleDateTimeFormat } from '@centreon/ui';
import { ListingVariant, type User, userAtom } from '@centreon/ui-context';

import { rs } from '@rstest/core';
import { render } from '@test-setup/render';
import { renderHook, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import dayjs from 'dayjs';
import { createStore, Provider } from 'jotai';
import { equals } from 'ramda';
import { config as transitionConfig } from 'react-transition-group';

import DateTimePickerInput from './DateTimePickerInput';
import { CustomTimePeriodProperty } from './models';

const retrievedUser = {
  alias: 'Admin alias',
  default_page: '/monitoring/resources',
  is_export_button_enabled: true,
  isExportButtonEnabled: true,
  locale: 'fr_FR.UTF8',
  name: 'Admin',
  use_deprecated_pages: false,
  user_interface_density: ListingVariant.compact
};

// The calendar slides for ~375 ms on every month change, and these tests
// change month 12 times. The setup only turns CSS transitions off; this turns
// the calendar's JavaScript-timed ones off too, for this file. The tests read
// the month the calendar ends on, which is the same.
beforeAll(() => {
  transitionConfig.disabled = true;
});

afterAll(() => {
  transitionConfig.disabled = false;
});

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

interface Day {
  day: string;
  indexDayInRowWeek: number;
  value: number;
}

interface MonthData {
  date: string;
  firstDay: Day;
  lastDay: Day;
  numberWeeks: number;
}

type Month = Record<string, MonthData>;

const months2023: Array<Month> = [
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

const month2023Reverse: Array<Month> = [...months2023].reverse();

interface TestData {
  button: string;
  data: Array<Month>;
  initialDate: string;
  // The date-time field shows the initial date in the user's timezone.
  initialDateInTimezone: string;
  timezone: string;
}

const testData: Array<TestData> = [
  {
    button: 'Next month',
    data: months2023,
    initialDate: '2023-01-01T12:59:41.041Z',
    initialDateInTimezone: '01/01/2023 01:59 PM',
    timezone: 'Europe/Paris'
  },
  {
    button: 'Previous month',
    data: month2023Reverse,
    initialDate: '2023-12-01T12:59:41.041Z',
    initialDateInTimezone: '12/01/2023 01:59 PM',
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

const getNextMonth = async ({
  currentMonth,
  labelButton
}: GetPreviousNextMonth): Promise<void> => {
  if (equals(currentMonth, 'December')) {
    return;
  }

  await userEvent.click(screen.getByLabelText(labelButton));
};

const getPreviousMonth = async ({
  currentMonth,
  labelButton
}: GetPreviousNextMonth): Promise<void> => {
  if (equals(currentMonth, 'January')) {
    return;
  }

  await userEvent.click(screen.getByLabelText(labelButton));
};

const checkIfDuplicateExists = (arr: Array<unknown>): boolean => {
  return new Set(arr).size !== arr.length;
};

const getMonth = (element: Month): { data: MonthData; name: string } => {
  const [name, data] = Object.entries(element)[0];

  return { data, name };
};

// The month the calendar should show for a date, as its header writes it.
const getMonthLabel = (date: string, timezone: string): string =>
  dayjs(date).tz(timezone).format('MMMM YYYY');

// The calendar slides from one month to the next: wait until it shows only
// the expected month before reading its days.
const waitForMonth = async ({
  date,
  timezone
}: {
  date: string;
  timezone: string;
}): Promise<void> => {
  await waitFor(() => {
    expect(
      screen.getByText(getMonthLabel(date, timezone), {
        selector: '.MuiPickersCalendarHeader-label'
      })
    ).toBeVisible();
    expect(document.querySelectorAll('[role="rowgroup"]')).toHaveLength(1);
  });
};

const getWeeks = (): Array<Element> =>
  Array.from(document.querySelectorAll('[role="rowgroup"]')).flatMap(
    (rowgroup) => Array.from(rowgroup.children)
  );

const getDays = (): Array<Element> =>
  getWeeks().flatMap((week) =>
    Array.from(week.children).filter((child) => child.tagName === 'BUTTON')
  );

const openCalendar = async (): Promise<void> => {
  const button = document.querySelector('button');

  if (!button) {
    throw new Error('No button to open the calendar');
  }

  await userEvent.click(button);
};

testData.forEach((item) => {
  const goToFollowingMonth = async (currentMonth: string): Promise<void> => {
    if (equals(item.button, ButtonCalendar.PMONTH)) {
      await getPreviousMonth({ currentMonth, labelButton: item.button });
    } else {
      await getNextMonth({ currentMonth, labelButton: item.button });
    }
  };

  describe(`DateTimePicker ${item.button}`, () => {
    beforeEach(() => {
      expect.hasAssertions();

      const store = createStore();
      store.set(userAtom, {
        ...retrievedUser,
        locale: 'en_US',
        timezone: item.timezone
      } as unknown as User);

      render(
        <Provider store={store}>
          <DateTimePickerInput
            changeDate={rs.fn()}
            date={new Date(item.initialDate)}
            property={CustomTimePeriodProperty.start}
          />
        </Provider>
      );
    });

    it(`displays the correct number of days for the current month when the ${item.button} button is clicked`, async () => {
      await openCalendar();

      for (const element of item.data) {
        const { data, name: currentMonth } = getMonth(element);

        await waitForMonth({ date: data.date, timezone: item.timezone });
        expect(getDays()).toHaveLength(data.lastDay.value);

        await goToFollowingMonth(currentMonth);
      }
    });

    it(`does not duplicate days in any of the month in the year when the ${item.button} button is clicked`, async () => {
      await openCalendar();

      for (const element of item.data) {
        const { data, name: currentMonth } = getMonth(element);

        await waitForMonth({ date: data.date, timezone: item.timezone });

        const daysInCurrentMonth = getDays().map((day) => day.textContent);

        expect(daysInCurrentMonth.length).toBeGreaterThan(0);
        expect(checkIfDuplicateExists(daysInCurrentMonth)).toBe(false);

        await goToFollowingMonth(currentMonth);
      }
    });

    it(`displays the first day as the start of the week when the ${item.button} button is clicked`, async () => {
      await openCalendar();

      for (const element of item.data) {
        const { data, name: currentMonth } = getMonth(element);
        const { firstDay, numberWeeks } = data;

        await waitForMonth({ date: data.date, timezone: item.timezone });

        const listWeeks = getWeeks();

        expect(listWeeks).toHaveLength(numberWeeks);

        const listDaysInFirstWeek = Array.from(listWeeks[0].children);

        expect(listDaysInFirstWeek).toHaveLength(numberDaysInWeek);
        expect(
          listDaysInFirstWeek[firstDay.indexDayInRowWeek]
        ).toHaveTextContent(String(firstDay.value));

        await goToFollowingMonth(currentMonth);
      }
    });

    it(`displays the last day as the end of the week when the ${item.button} button is clicked`, async () => {
      await openCalendar();

      for (const element of item.data) {
        const { data, name: currentMonth } = getMonth(element);
        const { lastDay, numberWeeks } = data;

        await waitForMonth({ date: data.date, timezone: item.timezone });

        const listWeeks = getWeeks();

        expect(listWeeks).toHaveLength(numberWeeks);

        const listDaysInLastWeek = Array.from(
          listWeeks[numberWeeks - 1].children
        );

        expect(listDaysInLastWeek).toHaveLength(numberDaysInWeek);
        expect(listDaysInLastWeek[lastDay.indexDayInRowWeek]).toHaveTextContent(
          String(lastDay.value)
        );

        await goToFollowingMonth(currentMonth);
      }
    });

    it(`displays the month and the year in the calendar's header when the ${item.button} button is clicked`, async () => {
      await openCalendar();
      const { result } = renderHook(() => useLocaleDateTimeFormat());

      for (const element of item.data) {
        const { data, name: currentMonth } = getMonth(element);

        const { format } = result.current;
        const monthAndYear = format({
          date: dayjs(data.date).tz(item.timezone).toDate(),
          formatString: 'MMMM YYYY'
        });

        expect(await screen.findByText(monthAndYear)).toBeInTheDocument();

        await goToFollowingMonth(currentMonth);
      }
    });

    it(`displays the correct day name on calendar's header when the ${item.button} button is clicked`, async () => {
      await openCalendar();
      const { result } = renderHook(() => useLocaleDateTimeFormat());

      for (const element of item.data) {
        const { data, name: currentMonth } = getMonth(element);
        const dateByTimeZone = dayjs(data.date).tz(item.timezone);
        const firstDay = dateByTimeZone.isUTC()
          ? dateByTimeZone.utc().startOf('month').startOf('week')
          : dateByTimeZone.startOf('month').startOf('week');

        const { format } = result.current;
        const daysArray = [0, 1, 2, 3, 4, 5, 6].map((diff) =>
          format({
            date: firstDay.add(diff, 'day').toDate(),
            formatString: 'dd'
          })
        );

        for (const day of daysArray) {
          expect(
            await screen.findByText(day.toUpperCase())
          ).toBeInTheDocument();
        }

        await goToFollowingMonth(currentMonth);
      }
    });

    it(`displays the calendar for the timezone ${item.timezone} when the ${item.button} button is clicked`, async () => {
      await openCalendar();

      for (const [index, element] of item.data.entries()) {
        const { name: currentMonth } = getMonth(element);

        await goToFollowingMonth(currentMonth);

        // After each click: the following month (the last one stays), the
        // initial date in the user's timezone in the field, and its hour
        // selected.
        const displayedMonth = getMonth(
          item.data[Math.min(index + 1, item.data.length - 1)]
        ).data;

        await waitForMonth({
          date: displayedMonth.date,
          timezone: item.timezone
        });
        expect(screen.getByRole('textbox')).toHaveValue(
          item.initialDateInTimezone
        );
        expect(getDays()).toHaveLength(displayedMonth.lastDay.value);
        expect(
          screen.getByRole('option', { name: '1 hours', selected: true })
        ).toBeVisible();
        expect(
          screen.getByRole('option', { name: 'PM', selected: true })
        ).toBeVisible();
      }
    });
  });
});
