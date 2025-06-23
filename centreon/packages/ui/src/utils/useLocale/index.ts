import { useAtomValue } from 'jotai';

import { browserLocaleAtom, userAtom } from '@centreon/ui-context';

export const useLocale = (): string => {
  const user = useAtomValue(userAtom);
  const browserLocale = useAtomValue(browserLocaleAtom);

  return user.locale || browserLocale;
};
