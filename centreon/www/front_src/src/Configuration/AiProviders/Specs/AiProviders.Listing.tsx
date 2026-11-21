import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import {
  labelAiProviders,
  labelAnthropic,
  labelMistral
} from '../translatedLabels';
import initialize from './initialize';

const getSwitch = (id: number): HTMLElement =>
  within(
    screen.getByTestId(listingDataTestIds.row.enableDisable(id))
  ).getByRole('checkbox');

export default (): void => {
  describe('Listing', () => {
    it('displays the AI provider configurations', async () => {
      initialize();

      await waitForRequest('@getAiProviders');

      expect(await screen.findByText(labelAiProviders)).toBeVisible();
      expect(await screen.findByText('Anthropic production')).toBeVisible();
      expect(screen.getByText('Mistral fallback')).toBeVisible();
      expect(screen.getByText(labelAnthropic)).toBeVisible();
      expect(screen.getByText(labelMistral)).toBeVisible();

      // the enabled/disabled switches reflect the providers state
      expect(getSwitch(1)).toBeChecked();
      expect(getSwitch(2)).not.toBeChecked();

      // the usage gauge shows the window closest to its cap; a provider
      // without pricing is not metered
      expect(
        screen.getByText('$41.00 / $50.00 (82%)', { exact: false })
      ).toBeVisible();
      expect(screen.getByText('Not metered')).toBeVisible();
    });

    it('sends an activation request when the switch of a disabled provider is clicked', async () => {
      initialize();

      await waitForRequest('@getAiProviders');

      await userEvent.click(
        await screen.findByTestId(listingDataTestIds.row.enableDisable(2))
      );

      const { request } = await waitForRequest('@enableAiProvider');

      expect(request.body).toEqual({ is_activated: true });
    });

    it('sends a listing request with a search parameter when a name is searched', async () => {
      initialize();

      await waitForRequest('@getAiProviders');

      const search = (await screen.findAllByPlaceholderText('Search'))[0];

      await userEvent.clear(search);
      await userEvent.type(search, 'mistral');

      // the search is debounced, and the same mock also answers the provider
      // count: look for the listing request carrying the typed name
      await waitFor(() =>
        expect(
          getRequestCalls('@getAiProviders').map(({ request }) =>
            decodeURIComponent(request.url.href)
          )
        ).toContainEqual(expect.stringContaining('name[lk]=mistral'))
      );
    });
  });
};
