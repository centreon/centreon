import { equals } from 'ramda';

import { useTheme } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

/**
 * Hook that a return an isDarkMode boolean value.
 * @returns {boolean} isDarkMode
 */
export const useThemeMode = (): { isDarkMode: boolean } => {
  const theme = useTheme();

  return { isDarkMode: equals(theme.palette.mode, ThemeMode.dark) };
};
