import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { act, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { searchAtom } from '../../Filter/filterAtoms';
import { listingAtom, sendingAtom } from '../../Listing/listingAtoms';
import { ResourceListing } from '../../models';
import { aiResultLoadingTestId } from '../AiResultDialog';
import AskAiFilter from '../AskAiFilter/AskAiFilter';
import { aiAlertCorrelationEndpoint } from '../api/endpoints';
import {
  labelAiBudgetExhausted,
  labelAiCouldNotTranslate,
  labelAskAi,
  labelAskAiApply,
  labelAskAiPlaceholder,
  labelAskAiUnsupported
} from '../translatedLabels';
import {
  closeDialog,
  expectTooltip,
  interceptSlowFirstRequest,
  waitForAllAnswers
} from './helpers';
import initialize from './initialize';
import {
  aiErrorMessage,
  derivedQuery,
  displayedListing,
  emptyFilterTranslationResponse,
  explainCurrentViewResponse,
  explainDerivedQueryResponse,
  filterTranslationResponse,
  getDerivedListing,
  model,
  unsupportedResponse
} from './utils';

type Store = ReturnType<typeof initialize>;

// What the resources listing does when it loads a filter, one render at a
// time: the listing lands, then the loading flag clears.
const loadListing = (store: Store, listing?: ResourceListing): void => {
  act(() => {
    store.set(sendingAtom, true);
  });
  act(() => {
    if (listing) {
      store.set(listingAtom, listing);
    }
  });
  act(() => {
    store.set(sendingAtom, false);
  });
};

const waitForSearch = (store: Store, search: string): Promise<void> =>
  waitFor(() => {
    // the filter rebuild can leave a trailing separator in the search string
    expect(store.get(searchAtom).trim()).toBe(search);
  });

const getCorrelatedNames = async (): Promise<Array<string>> => {
  const { request } = await waitForRequest('@correlateAlerts');

  return (request.body as { resources: Array<{ name: string }> }).resources.map(
    ({ name }) => name
  );
};

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

    it('explains the derived filter once its first page has loaded', async () => {
      const store = initialize(<AskAiFilter />, {
        filterTranslation: explainDerivedQueryResponse
      });
      store.set(listingAtom, displayedListing);

      await ask('why are the critical services failing?');

      await waitForRequest('@translateFilter');
      await waitForSearch(store, derivedQuery);

      // another page of the filter lands first: it is not explained
      loadListing(store, getDerivedListing(2));
      loadListing(store, getDerivedListing());

      expect(
        await screen.findByText('AI explanation — Disk-/var')
      ).toBeVisible();

      const { request } = await waitForRequest('@explainResource');

      expect((request.body as { name: string }).name).toBe('Disk-/var');
      expect(getRequestCalls('@explainResource')).toHaveLength(1);
      expect(getRequestCalls('@correlateAlerts')).toHaveLength(0);
    });

    it('drops the explanation of the derived filter when its listing fails to load', async () => {
      const store = initialize(<AskAiFilter />, {
        filterTranslation: explainDerivedQueryResponse
      });
      store.set(listingAtom, displayedListing);

      await ask('why are the critical services failing?');

      await waitForRequest('@translateFilter');
      await waitForSearch(store, derivedQuery);

      // the load settles without a listing
      loadListing(store);
      // a later refresh is not taken for the answer
      loadListing(store, getDerivedListing());

      await waitFor(() =>
        expect(store.get(listingAtom)).toEqual(getDerivedListing())
      );
      await new Promise((resolve) => {
        setTimeout(resolve, 100);
      });

      expect(screen.queryByRole('dialog')).toBeNull();
      expect(getRequestCalls('@explainResource')).toHaveLength(0);
      expect(getRequestCalls('@correlateAlerts')).toHaveLength(0);
    });

    it('explains the current view when the derived query is the current search', async () => {
      const store = initialize(<AskAiFilter />, {
        filterTranslation: explainDerivedQueryResponse
      });
      store.set(searchAtom, derivedQuery);
      store.set(listingAtom, displayedListing);

      await ask('why are the critical services failing?');

      expect(await getCorrelatedNames()).toEqual(['Ping', 'db-server-01']);
      expect(store.get(searchAtom)).toBe(derivedQuery);
    });

    it('warns when the question is neither a filter nor an explanation', async () => {
      initialize(<AskAiFilter />, { filterTranslation: unsupportedResponse });

      await ask('write me a poem');

      await waitForRequest('@translateFilter');

      expect(await screen.findByText(labelAskAiUnsupported)).toBeVisible();
    });

    it('shows the error only once when the translation fails', async () => {
      initialize(<AskAiFilter />, { failures: { translateFilter: 502 } });

      await ask('critical services');

      await waitForRequest('@translateFilter');

      expect(await screen.findByText(aiErrorMessage)).toBeVisible();
      expect(screen.getAllByText(aiErrorMessage)).toHaveLength(1);
      expect(screen.queryByText(labelAiCouldNotTranslate)).toBeNull();
    });

    it('closes the dialog and shows the error once when the explanation fails', async () => {
      const store = initialize(<AskAiFilter />, {
        failures: { correlateAlerts: 502 },
        filterTranslation: explainCurrentViewResponse
      });
      store.set(listingAtom, displayedListing);

      await ask('why is everything failing?');

      await waitForRequest('@correlateAlerts');

      expect(await screen.findByText(aiErrorMessage)).toBeVisible();
      await waitFor(() =>
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
      );
      expect(screen.queryByTestId(aiResultLoadingTestId)).toBeNull();
      expect(screen.getAllByText(aiErrorMessage)).toHaveLength(1);
    });

    it('ignores the late answer of a superseded explanation', async () => {
      const store = initialize(<AskAiFilter />, {
        filterTranslation: explainCurrentViewResponse
      });
      store.set(listingAtom, displayedListing);

      const releaseFirst = interceptSlowFirstRequest({
        alias: 'slowCorrelation',
        first: { body: { analysis: 'First analysis', model } },
        next: { analysis: 'Second analysis', model },
        path: aiAlertCorrelationEndpoint
      });

      await ask('why is everything failing?');
      expect(await screen.findByTestId(aiResultLoadingTestId)).toBeVisible();
      await closeDialog();

      await ask('why is everything failing?');
      expect(await screen.findByText('Second analysis')).toBeVisible();

      releaseFirst();
      await waitForAllAnswers('@slowCorrelation');

      expect(screen.getByText('Second analysis')).toBeVisible();
      expect(screen.queryByText('First analysis')).toBeNull();
    });

    it('is visible but disabled when the AI budget is exhausted', async () => {
      initialize(<AskAiFilter />, {
        availability: { is_available: false, reason: 'budget_exhausted' }
      });

      await waitForRequest('@getAiAvailability');

      const button = await screen.findByTestId(labelAskAi);

      expect(button).toBeDisabled();
      await expectTooltip(button, labelAiBudgetExhausted);
    });

    it('is hidden, without any error, when the user may not check the AI availability', async () => {
      initialize(<AskAiFilter />, { availabilityStatus: 403 });

      await waitForRequest('@getAiAvailability');

      await waitFor(() =>
        expect(screen.queryByTestId(labelAskAi)).not.toBeInTheDocument()
      );
      expect(screen.queryByText(aiErrorMessage)).toBeNull();
    });
  });
};
