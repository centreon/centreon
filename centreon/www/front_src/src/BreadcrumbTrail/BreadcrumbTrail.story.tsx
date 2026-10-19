import { SnackbarProvider } from '@centreon/ui';

import { useHydrateAtoms } from 'jotai/utils';
import { MemoryRouter } from 'react-router';

import menuData from '../../../../cypress/fixtures/menuData.json';
import menuDataWithAdditionalLabel from '../../../../cypress/fixtures/menuDataWithAdditionalLabel.json';
import navigationAtom from '../Navigation/navigationAtoms';
import Breadcrumbs from '.';

// A memory router on the current page replaces the useLocation stub of the
// Cypress spec; the navigation data is the one of the Cypress fixtures.
const Trail = ({ navigation }: { navigation: unknown }): JSX.Element => {
  useHydrateAtoms([[navigationAtom, navigation as typeof menuData]]);

  return (
    <SnackbarProvider>
      <MemoryRouter initialEntries={['/monitoring/resources']}>
        <Breadcrumbs />
      </MemoryRouter>
    </SnackbarProvider>
  );
};

export const Default = (): JSX.Element => <Trail navigation={menuData} />;

export const WithAdditionalLabel = (): JSX.Element => (
  <Trail navigation={menuDataWithAdditionalLabel} />
);
