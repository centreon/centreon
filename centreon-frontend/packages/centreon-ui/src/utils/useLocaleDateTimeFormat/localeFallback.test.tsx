import * as React from 'react';

import dayjs from 'dayjs';
import 'dayjs/locale/en';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';
import { useUpdateAtom } from 'jotai/utils';
import { render, RenderResult } from '@testing-library/react';
import { Provider } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import useLocaleDateTimeFormat from '.';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

let context;

const TestComponent = (): JSX.Element => {
  const localeDateTimeFormat = useLocaleDateTimeFormat();
  const setUser = useUpdateAtom(userAtom);

  React.useEffect(() => {
    setUser({
      alias: 'admin',
      defaultPage: '/monitoring/resources',
      isExportButtonEnabled: false,
      locale: 'unsupported_locale',
      name: 'admin',
      passwordRemainingTime: null,
      timezone: 'Europe/Paris',
      useDeprecatedPages: false,
    });
  }, []);

  context = localeDateTimeFormat;

  return <div />;
};

const renderLocaleDateTimeFormat = (): RenderResult => {
  return render(
    <Provider>
      <TestComponent />
    </Provider>,
  );
};

describe(useLocaleDateTimeFormat, () => {
  describe('toHumanizedDuration', () => {
    it('formats the given duration in English to a humanized duration when the locale is unsupported', () => {
      renderLocaleDateTimeFormat();

      const formattedDateTime = context.toHumanizedDuration(22141);

      expect(formattedDateTime).toEqual('6h 9m 1s');
    });
  });
});
