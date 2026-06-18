import { CssBaseline, createTheme, ThemeProvider } from '@mui/material';

import { ThemeMode } from '@centreon/ui-context';

import { beforeMount } from '@playwright/experimental-ct-react/hooks';
import i18n from 'i18next';
import { initReactI18next } from 'react-i18next';

import { getPalette } from '../packages/ui/src/ThemeProvider/palettes';

/**
 * Component-test mount entry point.
 *
 * - A minimal i18next instance is initialised with no resources: Centreon uses
 *   the English label as the translation key, so `t('Add a host')` resolves to
 *   "Add a host" — enough for component snapshots without bundling the app's
 *   translation catalogue.
 * - Every mounted component is wrapped in an MUI theme built from the Centreon
 *   palette (`getPalette`), so components that reference custom theme entries
 *   (e.g. `theme.palette.chip`) render correctly. We reuse only the palette —
 *   not the full `@centreon/ui` ThemeProvider — to avoid pulling its fonts and
 *   Tailwind stylesheet into the Vite build. The spec selects the mode via
 *   `mount(<Cmp/>, { hooksConfig: { theme: 'dark' } })`, the Playwright CT way
 *   of injecting providers (you cannot wrap the component inside the spec).
 */

export type HooksConfig = {
  theme?: 'light' | 'dark';
};

i18n.use(initReactI18next).init({
  fallbackLng: 'en',
  interpolation: { escapeValue: false },
  lng: 'en',
  resources: {}
});

beforeMount<HooksConfig>(async ({ App, hooksConfig }) => {
  const mode = hooksConfig?.theme === 'dark' ? ThemeMode.dark : ThemeMode.light;
  const theme = createTheme({ palette: getPalette(mode) });

  return (
    <ThemeProvider theme={theme}>
      <CssBaseline />
      <App />
    </ThemeProvider>
  );
});
