import { describe, expect, it } from '@rstest/core';
import { render } from '@testing-library/react';
import dayjs from 'dayjs';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import { Provider, useSetAtom } from 'jotai';
import { useEffect } from 'react';
import 'dayjs/locale/en';

import { ListingVariant, ThemeMode, userAtom } from '@centreon/ui-context';

import {
  type LocaleDateTimeFormat,
  useLocaleDateTimeFormat
} from '../packages/ui/src/utils/useLocaleDateTimeFormat';

/** Port of useLocaleDateTimeFormat/index.test.tsx (date/time formatting). */
dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

let context: LocaleDateTimeFormat;

const TestComponent = (): JSX.Element => {
  context = useLocaleDateTimeFormat();
  const setUser = useSetAtom(userAtom);

  useEffect(() => {
    setUser({
      alias: 'admin',
      canManageApiTokens: false,
      default_page: '/monitoring/resources',
      isExportButtonEnabled: false,
      locale: 'en',
      name: 'admin',
      themeMode: ThemeMode.light,
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: ListingVariant.compact
    });
  }, [setUser]);

  return <div />;
};

const renderHookComponent = (): void => {
  render(
    <Provider>
      <TestComponent />
    </Provider>
  );
};

const dateTime = '1995-12-17T03:24:00Z';

describe('useLocaleDateTimeFormat formatting (Rstest POC)', () => {
  it('formats a Date to date + time', () => {
    renderHookComponent();
    expect(context.toDateTime(new Date(dateTime))).toEqual(
      '12/17/1995 4:24 AM'
    );
  });

  it('formats a Date to a date', () => {
    renderHookComponent();
    expect(context.toDate(new Date(dateTime))).toEqual('12/17/1995');
  });

  it('formats a Date to a time', () => {
    renderHookComponent();
    expect(context.toTime(new Date(dateTime))).toEqual('4:24 AM');
  });

  it('formats a Date to an ISO string', () => {
    renderHookComponent();
    expect(context.toIsoString(new Date(dateTime))).toEqual(
      '1995-12-17T03:24:00Z'
    );
  });

  it('humanizes a duration', () => {
    renderHookComponent();
    expect(context.toHumanizedDuration(22141)).toEqual('6h 9m 1s');
  });
});
