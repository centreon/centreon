import dayjs from 'dayjs';

import 'dayjs/locale/en';

import { renderHook, RenderHookResult } from '@testing-library/react-hooks';
import timezonePlugin from 'dayjs/plugin/timezone';
import utcPlugin from 'dayjs/plugin/utc';
import localizedFormatPlugin from 'dayjs/plugin/localizedFormat';

import useLocaleDateTimeFormat, { LocaleDateTimeFormat } from '.';

dayjs.extend(timezonePlugin);
dayjs.extend(utcPlugin);
dayjs.extend(localizedFormatPlugin);

jest.mock('@centreon/ui-context', () => ({
  useUserContext: jest.fn().mockImplementation(() => ({
    locale: 'unsupported_locale',
    timezone: 'Europe/Paris',
  })),
}));

const renderUseLocaleDateTimeFormat = (): RenderHookResult<
  unknown,
  LocaleDateTimeFormat
> => {
  return renderHook(() => useLocaleDateTimeFormat());
};

describe(useLocaleDateTimeFormat, () => {
  describe('toHumanizedDuration', () => {
    it('formats the given duration in English to a humanized duration when the locale is unsupported', () => {
      const { result } = renderUseLocaleDateTimeFormat();

      const formattedDateTime = result.current.toHumanizedDuration(22141);

      expect(formattedDateTime).toEqual('6h 9m 1s');
    });
  });
});
