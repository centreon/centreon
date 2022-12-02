import React from 'react';

import { renderHook, act } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { LocalizationProvider } from '@mui/x-date-pickers';

import { userAtom } from '@centreon/ui-context/src';

import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';
import { CustomTimePeriodProperty } from '../../../Details/tabs/Graph/models';

import DateTimePickerInput from './DateTimePickerInput';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

const days = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
];

const date = '2023-02-01T12:59:41.041Z';
const timeZoneParis = 'Europe/Paris';
const Months2023 = [
  {
    January: {
      firstDay: { day: days[0], value: 1 },
      lastDay: { day: days[6], value: 31 },
    },
  },
  {
    February: {
      firstDay: { day: days[3], indexDayInRowWeek: 3, value: 1 },
      lastDay: { day: days[2], indexDayInRowWeek: 2, value: 28 },
      numberWeeks: 5,
    },
  },
];

const changeDate = (): void => undefined;
const setStart = undefined;
describe('calendar', () => {
  beforeEach(() => {
    const userData = renderHook(() => useAtomValue(userAtom));

    act(() => {
      userData.result.current.timezone = timeZoneParis;
      userData.result.current.locale = 'en_US';
    });
  });
  it('first test ,tz = europe/Paris', () => {
    const { result } = renderHook(() => useDateTimePickerAdapter());

    act(() => {
      const { Adapter } = result.current;
      cy.mount(
        <LocalizationProvider adapterLocale="en" dateAdapter={Adapter}>
          <DateTimePickerInput
            changeDate={changeDate}
            date={new Date(date)}
            property={CustomTimePeriodProperty.start}
            setDate={setStart}
          />
        </LocalizationProvider>,
      );
    });

    const currentDateByTimeZone = dayjs(date).tz(timeZoneParis);

    const dateInput = currentDateByTimeZone.format('L hh:mm A');
    const currentMonth = currentDateByTimeZone.format('M');
    const month = Months2023[Number(currentMonth) - 1];
    const { firstDay, lastDay, numberWeeks } = Object.values(month)[0];

    cy.get('input').should('have.value', dateInput);
    cy.get('input').click();
    cy.get('[role="rowgroup"]')
      .children()
      .each(($week, indexWeek, $listWeeks) => {
        if (indexWeek === 0) {
          cy.wrap($listWeeks[indexWeek])
            .children()
            .each(($elementDays, indexDays, $listDays) => {
              if (equals(indexDays, firstDay.indexDayInRowWeek)) {
                cy.wrap($listDays[firstDay.indexDayInRowWeek]).contains(
                  firstDay.value,
                );
              }
            });
        }
        if (equals(indexWeek, numberWeeks - 1)) {
          cy.wrap($listWeeks[indexWeek])
            .children()
            .each(($elementDays, indexDays, $listDays) => {
              if (equals(indexDays, lastDay.indexDayInRowWeek)) {
                cy.wrap($listDays[lastDay.indexDayInRowWeek]).contains(
                  lastDay.value,
                );
              }
            });
        }
      });
  });
});
