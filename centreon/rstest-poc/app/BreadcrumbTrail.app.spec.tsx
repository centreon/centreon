import { describe, expect, it } from '@rstest/core';
import { createStore, Provider } from 'jotai';
import { BrowserRouter } from 'react-router';

import menuData from '../../cypress/fixtures/menuData.json';
import menuDataWithAdditionalLabel from '../../cypress/fixtures/menuDataWithAdditionalLabel.json';
import SnackbarProvider from '../../packages/ui/src/Snackbar/SnackbarProvider';
import {
  ListingVariant,
  ThemeMode,
  userAtom
} from '../../packages/ui-context/src';
import Breadcrumbs, { router } from '../../www/front_src/src/BreadcrumbTrail';
import navigationAtom from '../../www/front_src/src/Navigation/navigationAtoms';
import { renderApp, screen } from './render';

/**
 * Phase 0b port: a routing-dependent component (BrowserRouter + a module-level
 * `useLocation` spied via rstest.spyOn). Validates that routing works under
 * Rstest jsdom.
 *
 * NOTE: the third Cypress test (hover reveals the copy icon via CSS opacity,
 * then writes to the clipboard) is intentionally NOT ported — CSS `:hover`
 * opacity and clipboard are real-browser concerns and belong in Browser Mode /
 * Chromatic, not jsdom.
 */
const renderBreadcrumbs = (data: unknown): void => {
  const store = createStore();
  store.set(userAtom, {
    alias: 'admin',
    default_page: '/monitoring/resources',
    isExportButtonEnabled: true,
    locale: 'en',
    name: 'admin',
    themeMode: ThemeMode.light,
    timezone: 'Europe/Paris',
    use_deprecated_pages: false,
    user_interface_density: ListingVariant.compact
  });
  store.set(navigationAtom, data);

  router.useLocation = () =>
    ({ pathname: '/monitoring/resources' }) as ReturnType<
      typeof router.useLocation
    >;

  renderApp(
    <SnackbarProvider>
      <BrowserRouter>
        <Provider store={store}>
          <Breadcrumbs />
        </Provider>
      </BrowserRouter>
    </SnackbarProvider>
  );
};

describe('BreadcrumbTrail (Rstest app POC)', () => {
  it('displays the breadcrumb trail', () => {
    renderBreadcrumbs(menuData);

    const links = screen.getAllByRole('link');
    expect(links[0]).toHaveTextContent('Monitoring');
    expect(links[0]).toHaveAttribute('href', '/monitoring/resources');
    expect(links[1]).toHaveTextContent('Resources Status');
    expect(links[1]).toHaveAttribute('href', '/monitoring/resources');
  });

  it('displays the breadcrumb trail with an additional label', () => {
    renderBreadcrumbs(menuDataWithAdditionalLabel);

    expect(screen.getByText('BETA')).toBeVisible();
  });
});
