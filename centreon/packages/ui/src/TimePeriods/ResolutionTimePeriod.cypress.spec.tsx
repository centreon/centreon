import dayjs from 'dayjs';
import 'dayjs/locale/en';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { Provider, createStore } from 'jotai';

import { Switch } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { retrievedUser } from './mockedData';

import TimePeriod from './index';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

const data = [
  {
    locale: 'en_US',
    resolution: { height: 590, width: 500 },
    timezone: 'Europe/Paris'
  },
  {
    locale: 'en_US',
    resolution: { height: 590, width: 200 },
    timezone: 'Europe/Paris'
  },
  {
    locale: 'en_US',
    resolution: { height: 590, width: 1024 },
    timezone: 'Europe/Paris'
  }
];

data.forEach((item) =>
  describe('Time period', () => {
    const { height, width } = item.resolution;
    beforeEach(() => {
      cy.viewport(width, height);

      const store = createStore();

      store.set(userAtom, {
        ...retrievedUser,
        locale: 'en_US',
        timezone: 'Europe/Paris'
      });

      cy.mount({
        Component: (
          <Provider store={store}>
            <TimePeriod renderExternalComponent={<Switch />} />
          </Provider>
        )
      });
    });

    it(`displays correctly the dates design when screen resolution is ${width}px`, () => {
      cy.contains('1 day').should('be.visible');
      cy.contains('7 days').should('be.visible');
      cy.contains('31 days').should('be.visible');
      cy.contains('From').should('be.visible');
      cy.contains('To').should('be.visible');

      cy.makeSnapshotWithCustomResolution({
        resolution: { height, width },
        title: `${width}px`
      });
    });
  })
);
