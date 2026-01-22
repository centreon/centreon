import { ThemeMode, userAtom } from '@centreon/ui-context';

import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTransition } from 'react';

const useSwitchThemeMode = (): [
  isDarkMode: boolean,
  isPending: boolean,
  themeMode: ThemeMode,
  updateUser: () => void
] => {
  const [user, setUser] = useAtom(userAtom);
  const isDarkMode = equals(user.themeMode, ThemeMode.dark);
  const [isPending, startTransition] = useTransition();

  const themeMode = isDarkMode ? ThemeMode.light : ThemeMode.dark;
  const updateUser = (): void => {
    if (themeMode === ThemeMode.dark) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    startTransition(() => {
      setUser({
        ...user,
        themeMode
      });
    });
  };

  return [isPending, isDarkMode, themeMode, updateUser];
};

export default useSwitchThemeMode;
