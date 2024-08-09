import { useMemo } from 'react';

import { useAtomValue } from 'jotai';

import { userAtom } from '@centreon/ui-context';

import { PanelOptions } from './models';

export const useGetLocaleAndTimezone = ({
  locale,
  timezone
}: Pick<PanelOptions, 'locale' | 'timezone'>): {
  locale: string;
  timezone: string;
} => {
  const user = useAtomValue(userAtom);

  const timezoneToUse = useMemo(
    () => (timezone?.id ?? user.timezone) as string,
    [user.timezone, timezone]
  );
  const localeToUse = useMemo(
    () => (locale?.id ?? user.locale.replace('_', '-')) as string,
    [user.locale, locale]
  );

  return {
    locale: localeToUse,
    timezone: timezoneToUse
  };
};
