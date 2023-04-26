import { useEffect } from 'react';

import dayjs from 'dayjs';
import 'dayjs/locale/en';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import { render, RenderResult } from '@testing-library/react';
import { Provider, useSetAtom } from 'jotai';

import { userAtom, ThemeMode } from '@centreon/ui-context';

import useLocaleDateTimeFormat from '.';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

let context;

const TestComponent = (): JSX.Element => {
  const localeDateTimeFormat = useLocaleDateTimeFormat();
  const setUser = useSetAtom(userAtom);

  useEffect(() => {
    setUser({
      alias: 'admin',
      default_page: '/monitoring/resources',
      isExportButtonEnabled: false,
      locale: 'en',
      name: 'admin',
      themeMode: ThemeMode.light,
      timezone: 'Europe/Paris',
      use_deprecated_pages: false
    });
  }, []);

  context = localeDateTimeFormat;

  return <div />;
};

const renderLocaleDateTimeFormat = (): RenderResult => {
  return render(
    <Provider>
      <TestComponent />
    </Provider>
  );
};

const dateTime = '1995-12-17T03:24:00Z';

describe(useLocaleDateTimeFormat, () => {
  describe('toDateTime', () => {
    it('formats the given Date to a string showing the date and the time', () => {
      renderLocaleDateTimeFormat();

      const formattedDateTime = context.toDateTime(new Date(dateTime));

      expect(formattedDateTime).toEqual('12/17/1995 4:24 AM');
    });
  });

  describe('toDate', () => {
    it('formats the given Date to a string showing the date', () => {
      renderLocaleDateTimeFormat();

      const formattedDateTime = context.toDate(new Date(dateTime));

      expect(formattedDateTime).toEqual('12/17/1995');
    });
  });

  describe('toTime', () => {
    it('formats the given Date to a string showing the time', () => {
      renderLocaleDateTimeFormat();

      const formattedDateTime = context.toTime(new Date(dateTime));

      expect(formattedDateTime).toEqual('4:24 AM');
    });
  });

  describe('toIsoString', () => {
    it('formats the given Date to an ISO complient string', () => {
      renderLocaleDateTimeFormat();

      const formattedDateTime = context.toIsoString(new Date(dateTime));

      expect(formattedDateTime).toEqual('1995-12-17T03:24:00Z');
    });
  });

  describe('toHumanizedDuration', () => {
    it('formats the given duration to a humanized duration', () => {
      renderLocaleDateTimeFormat();

      const formattedDateTime = context.toHumanizedDuration(22141);

      expect(formattedDateTime).toEqual('6h 9m 1s');
    });
  });
});
