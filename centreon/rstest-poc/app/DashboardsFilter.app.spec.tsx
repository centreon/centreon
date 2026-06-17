import { describe, expect, it } from '@rstest/core';
import userEvent from '@testing-library/user-event';
import { createStore, Provider } from 'jotai';

import Filter from '../../www/front_src/src/Dashboards/components/DashboardLibrary/DashboardListing/Actions/Filter';
import { searchAtom } from '../../www/front_src/src/Dashboards/components/DashboardLibrary/DashboardListing/atom';
import { labelClearFilter } from '../../www/front_src/src/Dashboards/components/DashboardLibrary/DashboardListing/translatedLabels';
import { renderApp, screen } from './render';

/** Phase 0b port: a Jotai-state-driven filter (search input + clear button). */
const renderFilter = (): void => {
  const store = createStore();
  store.set(searchAtom, '');

  renderApp(
    <Provider store={store}>
      <Filter />
    </Provider>
  );
};

describe('DashboardsFilter (Rstest app POC)', () => {
  it('renders the search input with a placeholder', () => {
    renderFilter();

    expect(screen.getByRole('textbox')).toHaveAttribute(
      'placeholder',
      'Search'
    );
  });

  it('clears the filter when the clear button is clicked', async () => {
    renderFilter();

    const input = screen.getByRole('textbox');
    await userEvent.type(input, 'Dashboard 1');
    expect(input).toHaveValue('Dashboard 1');

    await userEvent.click(screen.getByTestId(labelClearFilter));

    expect(input).toHaveValue('');
  });
});
