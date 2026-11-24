import {
  getRequestCalls,
  interceptAPIRequest,
  Method,
  waitForRequest
} from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import { labelResourceEnabled } from '../../ConfigurationBase/translatedLabels';
import { ResourceType } from '../../models';
import { getAiProviderEndpoint } from '../api';
import {
  labelAiProviders,
  labelAnthropic,
  labelMistral
} from '../translatedLabels';
import initialize from './initialize';

const activationConflictMessage =
  'Another AI provider was enabled meanwhile, try again';

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

    it('shows why a provider could not be enabled and matches the switches to the server', async () => {
      initialize();

      await waitForRequest('@getAiProviders');

      // another provider was enabled meanwhile
      interceptAPIRequest({
        alias: 'enableAiProviderConflict',
        method: Method.PATCH,
        path: `**${getAiProviderEndpoint({ id: 2 })}`,
        response: { code: 409, message: activationConflictMessage },
        statusCode: 409
      });

      const listingRequests = getRequestCalls('@getAiProviders').length;

      // a success message may show only briefly: watch for it from the click
      const successMessage = labelResourceEnabled(ResourceType.AiProvider);
      let hasShownSuccess = false;
      const observer = new MutationObserver(() => {
        hasShownSuccess ||= (document.body.textContent ?? '').includes(
          successMessage
        );
      });
      observer.observe(document.body, {
        characterData: true,
        childList: true,
        subtree: true
      });

      await userEvent.click(
        await screen.findByTestId(listingDataTestIds.row.enableDisable(2))
      );

      await waitForRequest('@enableAiProviderConflict');

      expect(await screen.findByText(activationConflictMessage)).toBeVisible();

      // the listing is loaded again, and the switch shows the server state
      await waitFor(() =>
        expect(getRequestCalls('@getAiProviders').length).toBeGreaterThan(
          listingRequests
        )
      );
      await waitFor(() => expect(getSwitch(2)).not.toBeChecked());
      expect(getSwitch(1)).toBeChecked();
      observer.disconnect();
      // a refused activation is not reported as a success
      expect(hasShownSuccess).toBe(false);
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
