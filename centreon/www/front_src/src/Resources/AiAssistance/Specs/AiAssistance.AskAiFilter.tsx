import { waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { searchAtom } from '../../Filter/filterAtoms';
import { listingAtom } from '../../Listing/listingAtoms';
import AskAiFilter from '../AskAiFilter/AskAiFilter';
import {
  labelAiCouldNotTranslate,
  labelAskAi,
  labelAskAiApply,
  labelAskAiPlaceholder
} from '../translatedLabels';
import initialize from './initialize';
import {
  displayedListing,
  emptyFilterTranslationResponse,
  explainCurrentViewResponse,
  filterTranslationResponse,
  model
} from './utils';

const ask = async (prompt: string): Promise<void> => {
  await userEvent.click(await screen.findByTestId(labelAskAi));
  await userEvent.type(
    await screen.findByTestId(labelAskAiPlaceholder),
    prompt
  );
  await userEvent.click(screen.getByTestId(labelAskAiApply));
};

export default (): void => {
  describe('AskAiFilter', () => {
    it('applies the translated search expression for a filter intent', async () => {
      const store = initialize(<AskAiFilter />, {
        filterTranslation: filterTranslationResponse
      });

      await ask('critical services');

      const { request } = await waitForRequest('@translateFilter');

      expect((request.body as { prompt: string }).prompt).toBe(
        'critical services'
      );

      await waitFor(() => {
        // the filter rebuild can leave a trailing separator in the search string
        expect(store.get(searchAtom).trim()).toBe(
          'type:service status:critical'
        );
      });
    });

    it('warns when no filter could be derived', async () => {
      initialize(<AskAiFilter />, {
        filterTranslation: emptyFilterTranslationResponse
      });

      await ask('hello there');

      await waitForRequest('@translateFilter');

      expect(await screen.findByText(labelAiCouldNotTranslate)).toBeVisible();
    });

    it('explains the current view for an explain intent', async () => {
      const store = initialize(<AskAiFilter />, {
        filterTranslation: explainCurrentViewResponse
      });
      store.set(listingAtom, displayedListing);

      await ask('why is everything failing?');

      const { request } = await waitForRequest('@correlateAlerts');

      expect(
        (request.body as { resources: Array<unknown> }).resources
      ).toHaveLength(2);

      expect(
        await screen.findByText('The host db-server-01 is down', {
          exact: false
        })
      ).toBeVisible();
      expect(await screen.findByText(model, { exact: false })).toBeVisible();
    });
  });
};
