import {
  labelClearFilter,
  labelSearch
} from '../../www/front_src/src/Dashboards/components/DashboardLibrary/DashboardListing/translatedLabels';
import { expect, test } from './fixtures';

const storyId = 'DashboardListing/Actions/Filter/Default';

test('renders the search input with a placeholder', async ({ mount }) => {
  const component = await mount(storyId);

  await expect(component.getByPlaceholder(labelSearch)).toBeVisible();
});

test('commits the typed search to the store', async ({ mount }) => {
  const component = await mount(storyId);

  await component.getByPlaceholder(labelSearch).fill('Dashboard 1');

  // The store update is debounced by 500ms: toHaveValue retries until it lands.
  await expect(component.getByTestId('search')).toHaveValue('Dashboard 1');
});

test('clears the filter when clicking the clear button', async ({ mount }) => {
  const component = await mount(storyId);
  const searchField = component.getByPlaceholder(labelSearch);

  await searchField.fill('Dashboard 1');
  await expect(component.getByTestId('search')).toHaveValue('Dashboard 1');

  await component.getByTestId(labelClearFilter).click();

  await expect(searchField).toHaveValue('');
  await expect(component.getByTestId('search')).toHaveValue('');
});
