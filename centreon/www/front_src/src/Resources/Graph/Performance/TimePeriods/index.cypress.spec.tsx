import React from 'react';

import { renderHook, act } from '@testing-library/react-hooks/dom';
import dayjs from 'dayjs';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { LocalizationProvider } from '@mui/x-date-pickers';

import { userAtom } from '@centreon/ui-context';

import useDateTimePickerAdapter from '../../../useDateTimePickerAdapter';
import { CustomTimePeriodProperty } from '../../../Details/tabs/Graph/models';

import DateTimePickerInput from './DateTimePickerInput';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

const date = '2023-02-01T12:59:41.041Z';
const timeZoneParis = 'Europe/Paris';
const numberDaysInWeek = 7;

const days = [
  'Sunday',
  'Monday',
  'Tuesday',
  'Wednesday',
  'Thursday',
  'Friday',
  'Saturday',
];

const months2023 = [
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
describe('calendar for timeZone=Europe/Paris', () => {
  before(() => {
    const userData = renderHook(() => useAtomValue(userAtom));

    act(() => {
      userData.result.current.timezone = timeZoneParis;
      userData.result.current.locale = 'en_US';
    });
  });

  beforeEach(() => {
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
  });

  // it('date must be correct in input calendar', () => {
  //   const currentDateByTimeZone = dayjs(date).tz(timeZoneParis);

  //   const dateInput = currentDateByTimeZone.format('L hh:mm A');
  //   cy.get('input').should('have.value', dateInput);
  // });

  it.only('the first/last day of selected month ,must correspond to the correct beginning/end of the week', () => {
    const { firstDay, lastDay, numberWeeks } = Object.values(months2023[1])[0];

    cy.get('input').click();

    cy.get('[role="rowgroup"]').children().as('listWeeks');

    cy.get('@listWeeks').should('have.length', numberWeeks);
    cy.get('@listWeeks').eq(0).as('firstWeek');
    cy.get('@firstWeek').children().as('listDaysInFirstWeek');

    cy.get('@listDaysInFirstWeek').should('have.length', numberDaysInWeek);
    cy.get('@listDaysInFirstWeek')
      .eq(firstDay.indexDayInRowWeek)
      .as('firstDayInWeek');
    cy.get('@firstDayInWeek').contains(firstDay.value);

    cy.get('@listWeeks')
      .eq(numberWeeks - 1)
      .as('lastWeek');

    cy.get('@lastWeek').children().as('listDaysInLastWeek');
    cy.get('@listDaysInLastWeek').should('have.length', numberDaysInWeek);

    cy.get('@listDaysInLastWeek')
      .eq(lastDay.indexDayInRowWeek)
      .as('lastDayInWeek');
    cy.get('@lastDayInWeek').contains(lastDay.value);
  });
});
