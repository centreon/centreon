import { platformVersionsAtom } from '@centreon/ui-context';

import { useHydrateAtoms } from 'jotai/utils';

import type { PlatformVersions } from '../api/models';
import About from './About';

const platformVersions: PlatformVersions = {
  modules: {},
  web: { fix: '0', major: '23', minor: '04', version: '23.04.0' },
  widgets: {}
};

// Default store, shared with the gallery ThemeProvider: the page follows the
// emulated color scheme like the rest of the application.
export const Default = (): JSX.Element => {
  useHydrateAtoms([[platformVersionsAtom, platformVersions]]);

  return <About />;
};
