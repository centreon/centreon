import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { render } from '@test-setup/render';
import { screen } from '@testing-library/react';
import i18next from 'i18next';
import { atom, createStore, Provider } from 'jotai';
import { initReactI18next } from 'react-i18next';

import type { FiltersPanelWidth } from '../../../models';
import { configurationAtom } from '../../atoms';
import Filters from './Filters';

// No platform page size is set, so the panel sends no listing request.
const renderPanel = (filtersPanelWidth?: FiltersPanelWidth): HTMLElement => {
  i18next.use(initReactI18next).init({ lng: 'en', resources: {} });

  const store = createStore();

  store.set(configurationAtom, {
    api: { endpoints: { getAll: '/resources' } },
    defaultSelectedColumnIds: [],
    filtersConfiguration: [],
    filtersInitialValues: { name: '' },
    filtersPanelWidth,
    resourceType: null
  });

  render(
    <QueryClientProvider client={new QueryClient()}>
      <Provider store={store}>
        <Filters filtersAtom={atom({ name: '' })} filtersAtomKey="filters" />
      </Provider>
    </QueryClientProvider>
  );

  return screen.getByTestId('advanced-filters');
};

describe('Advanced filters panel: ', () => {
  beforeEach(() => {
    expect.hasAssertions();
  });

  it.each([
    [60, 480],
    [undefined, 336]
  ])(
    'is %s spacing units wide (%spx), clear of the field above',
    (width, pixels) => {
      const panel = renderPanel(width);
      const style = getComputedStyle(panel);

      expect(panel.getBoundingClientRect().width).toBe(pixels);
      expect(style.marginTop).toBe('8px');
      expect(style.padding).toBe('16px');
      expect(style.rowGap).toBe('12px');
    }
  );

  it('has no top margin at full width', () => {
    expect(getComputedStyle(renderPanel('full')).marginTop).toBe('0px');
  });
});
