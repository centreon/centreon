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
  useUserContext: jest
    .fn()
    .mockImplementation(() => ({ locale: 'en', timezone: 'Europe/Paris' })),
}));

const renderUseLocaleDateTimeFormat = (): RenderHookResult<
  unknown,
  LocaleDateTimeFormat
> => {
  return renderHook(() => useLocaleDateTimeFormat());
};

const dateTime = '1995-12-17T03:24:00Z';

describe(useLocaleDateTimeFormat, () => {
  describe('toDateTime', () => {
    it('formats the given Date to a string showing the date and the time', () => {
      const { result } = renderUseLocaleDateTimeFormat();

      const formattedDateTime = result.current.toDateTime(new Date(dateTime));

      expect(formattedDateTime).toEqual('12/17/1995 04:24');
    });
  });

  describe('toDate', () => {
    it('formats the given Date to a string showing the date', () => {
      const { result } = renderUseLocaleDateTimeFormat();

      const formattedDateTime = result.current.toDate(new Date(dateTime));

      expect(formattedDateTime).toEqual('12/17/1995');
    });
  });

  describe('toTime', () => {
    it('formats the given Date to a string showing the time', () => {
      const { result } = renderUseLocaleDateTimeFormat();

      const formattedDateTime = result.current.toTime(new Date(dateTime));

      expect(formattedDateTime).toEqual('04:24');
    });
  });

  describe('toIsoString', () => {
    it('formats the given Date to an ISO complient string', () => {
      const { result } = renderUseLocaleDateTimeFormat();

      const formattedDateTime = result.current.toIsoString(new Date(dateTime));

      expect(formattedDateTime).toEqual('1995-12-17T03:24:00Z');
    });
  });
});
