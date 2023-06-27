import dayjs from 'dayjs';
import 'dayjs/locale/fr';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { Provider, createStore } from 'jotai';

import { Switch } from '@mui/material';

import { userAtom } from '@centreon/ui-context';

import { retrievedUser } from './mockedData';

import TimePeriod from '.';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

const data = [
  {
    locale: 'fr_FR',
    resolution: { height: 720, width: 500 },
    timezone: 'Europe/Paris'
  },
  {
    locale: 'fr_FR',
    resolution: { height: 720, width: 200 },
    timezone: 'Europe/Paris'
  },
  {
    locale: 'fr_FR',
    resolution: { height: 720, width: 1024 },
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
        locale: 'fr_FR',
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
      cy.matchImageSnapshot(`${width}px`);
    });
  })
);
