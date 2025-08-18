import { browserLocaleAtom, userAtom } from '@centreon/ui-context';
import { useAtomValue } from 'jotai';

export const useLocale = () => {
  const user = useAtomValue(userAtom);
  const browserLocale = useAtomValue(browserLocaleAtom);

  return user.locale || browserLocale;
};
