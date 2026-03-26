import { useLocale } from '@centreon/ui';
import { userAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { useMemo } from 'react';

import { PanelOptions } from './models';

export const useGetLocaleAndTimezone = ({
  locale,
  timezone
}: Pick<PanelOptions, 'locale' | 'timezone'>): {
  locale: string;
  timezone: string;
} => {
  const user = useAtomValue(userAtom);
  const userLocale = useLocale();

  const timezoneToUse = useMemo(
    () => (timezone?.id ?? user.timezone) as string,
    [user.timezone, timezone]
  );
  const localeToUse = useMemo(
    () => (locale?.id ?? userLocale.replace('_', '-')) as string,
    [user.locale, locale]
  );

  return {
    locale: localeToUse,
    timezone: timezoneToUse
  };
};
