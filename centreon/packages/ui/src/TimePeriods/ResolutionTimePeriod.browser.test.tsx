import dayjs from 'dayjs';
import 'dayjs/locale/en';

import { Switch } from '@mui/material';

import { type User, userAtom } from '@centreon/ui-context';

import { render } from '@test-setup/render';
import { screen, within } from '@testing-library/react';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { createStore, Provider } from 'jotai';

import TimePeriod from './index';
import { retrievedUser } from './mockedData';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

const data = [
  {
    labels: ['1 day', '7 days', '31 days'],
    locale: 'en_US',
    resolution: { height: 590, width: 500 },
    timezone: 'Europe/Paris'
  },
  {
    labels: ['1 day', '7 days', '31 days'],
    locale: 'en_US',
    resolution: { height: 590, width: 200 },
    timezone: 'Europe/Paris'
  },
  {
    labels: ['Last day', 'Last 7 days', 'Last 31 days'],
    locale: 'en_US',
    resolution: { height: 590, width: 1024 },
    timezone: 'Europe/Paris'
  }
];

// The date format of the en_US locale, e.g. "01/23/2024 4:27 PM".
const datePattern = /^\d{2}\/\d{2}\/\d{4} \d{1,2}:\d{2} (AM|PM)$/;

const getRect = (element: Element): DOMRect => element.getBoundingClientRect();

data.forEach((item) => {
  describe('Time period', () => {
    const { width } = item.resolution;

    beforeEach(() => {
      expect.hasAssertions();

      const store = createStore();

      // The legacy snake_case fixture of the Cypress spec: a partial user.
      store.set(userAtom, {
        ...retrievedUser,
        locale: 'en_US',
        timezone: 'Europe/Paris'
      } as unknown as User);

      // Cypress set the viewport width; the runner's viewport is fixed, so
      // the component gets a parent of that width instead. It measures its
      // parent and condenses below 600px wide, so the layout is the same.
      render(
        <div style={{ width }}>
          <Provider store={store}>
            <TimePeriod renderExternalComponent={<Switch />} />
          </Provider>
        </div>
      );
    });

    it(`displays correctly the dates design when screen resolution is ${width}px`, async () => {
      // `cy.contains` matched a substring: "1 day" is found in "Last 31 days".
      expect(
        (await screen.findAllByText('1 day', { exact: false }))[0]
      ).toBeVisible();
      expect(screen.getAllByText('7 days', { exact: false })[0]).toBeVisible();
      expect(screen.getAllByText('31 days', { exact: false })[0]).toBeVisible();
      expect(screen.getByText('From', { exact: false })).toBeVisible();
      expect(screen.getByText('To', { exact: false })).toBeVisible();

      // Replaces the visual snapshot.
      // The short or the long labels, in order, the first one selected.
      const timePeriodGroup = screen.getByRole('group');
      const timePeriodButtons = within(timePeriodGroup).getAllByRole('button');

      expect(timePeriodButtons.map((button) => button.textContent)).toEqual(
        item.labels
      );
      expect(timePeriodButtons[0]).toHaveClass('MuiButton-contained');
      expect(timePeriodButtons[1]).toHaveClass('MuiButton-outlined');
      expect(timePeriodButtons[2]).toHaveClass('MuiButton-outlined');

      // The custom period with its two dates, stacked when condensed.
      const from = screen.getByText('From:');
      const to = screen.getByText('To:');
      const fromDate = from.nextElementSibling as HTMLElement;
      const toDate = to.nextElementSibling as HTMLElement;

      expect(fromDate).toHaveTextContent(datePattern);
      expect(toDate).toHaveTextContent(datePattern);

      const isCondensed = width < 600;

      if (isCondensed) {
        expect(getRect(to).top).toBeGreaterThanOrEqual(getRect(from).bottom);
      } else {
        expect(getRect(to).top).toBe(getRect(from).top);
        expect(getRect(to).left).toBeGreaterThanOrEqual(
          getRect(fromDate).right
        );
      }

      // The external component, on the same line as the rest, or wrapped
      // below them when the space is too narrow.
      const externalSwitch = screen
        .getByRole('checkbox')
        .closest('.MuiSwitch-root') as HTMLElement;
      const customPeriod = screen.getByTestId('Compact time period');

      expect(externalSwitch).toBeVisible();

      if (width < 300) {
        expect(getRect(customPeriod).top).toBeGreaterThanOrEqual(
          getRect(timePeriodGroup).bottom
        );
        expect(getRect(externalSwitch).top).toBeGreaterThanOrEqual(
          getRect(customPeriod).bottom
        );
      } else {
        expect(getRect(customPeriod).left).toBeGreaterThan(
          getRect(timePeriodGroup).right
        );
        expect(getRect(externalSwitch).left).toBeGreaterThan(
          getRect(customPeriod).right
        );
      }
    });
  });
});
