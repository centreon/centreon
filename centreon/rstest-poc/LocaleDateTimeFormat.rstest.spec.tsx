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

/**
 * Port of useLocaleDateTimeFormat/localeFallback.test.tsx to Rstest.
 * Demonstrates testing a hook with Jotai state and dayjs — no DOM rendering of
 * a styled component, so this is the "fast logic" layer.
 */
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
      locale: 'unsupported_locale',
      name: 'admin',
      themeMode: ThemeMode.light,
      timezone: 'Europe/Paris',
      use_deprecated_pages: false,
      user_interface_density: ListingVariant.compact
    });
  }, [setUser]);

  return <div />;
};

describe('useLocaleDateTimeFormat (Rstest POC)', () => {
  it('humanizes a duration in English when the locale is unsupported', () => {
    render(
      <Provider>
        <TestComponent />
      </Provider>
    );

    expect(context.toHumanizedDuration(22141)).toEqual('6h 9m 1s');
  });
});
