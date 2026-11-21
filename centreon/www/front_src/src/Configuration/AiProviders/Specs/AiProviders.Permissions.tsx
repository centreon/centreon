import { screen, waitFor, within } from '@testing-library/react';

import { listingDataTestIds } from '../../ConfigurationBase/Listing/dataTestIds';
import {
  labelApiToken,
  labelCurrency,
  labelDailyLimit,
  labelDefaultModel,
  labelDefaultModelInputPrice,
  labelFastModel,
  labelFetchModels,
  labelFetchPrices,
  labelTestConnection,
  labelType
} from '../translatedLabels';
import {
  descriptionTestId,
  getApiToken,
  getCustomInput,
  getInput,
  getName,
  getSaveButton,
  initializeListing,
  isActivatedTestId,
  openProvider
} from './helpers';

// the custom fields of the form, each disabled without the write permission
const customFields = [
  labelType,
  labelDefaultModel,
  labelFastModel,
  labelCurrency,
  labelDefaultModelInputPrice,
  labelDailyLimit
];

const providerActions = [
  labelTestConnection,
  labelFetchModels,
  labelFetchPrices
];

export default (): void => {
  describe('Write permission', () => {
    it('offers the add, delete and enable actions with the AI provider write permission', async () => {
      await initializeListing({ hasWriteAccess: true });

      expect(await screen.findByText('Anthropic production')).toBeVisible();

      expect(screen.getByTestId('add-resource')).toBeEnabled();
      expect(
        screen.getByTestId(listingDataTestIds.row.delete(1))
      ).toBeEnabled();
      for (const id of [1, 2]) {
        expect(
          within(
            screen.getByTestId(listingDataTestIds.row.enableDisable(id))
          ).getByRole('checkbox')
        ).toBeEnabled();
      }
    });

    it('offers no add, delete or enable action without the AI provider write permission', async () => {
      await initializeListing({ hasWriteAccess: false });

      expect(await screen.findByText('Anthropic production')).toBeVisible();
      expect(screen.getByText('Mistral fallback')).toBeVisible();

      expect(screen.queryByTestId('add-resource')).not.toBeInTheDocument();
      for (const id of [1, 2]) {
        expect(
          screen.queryByTestId(listingDataTestIds.row.delete(id))
        ).not.toBeInTheDocument();
        expect(
          screen.queryByTestId(listingDataTestIds.row.enableDisable(id))
        ).not.toBeInTheDocument();
      }
    });

    it('opens an editable form with the AI provider write permission', async () => {
      await openProvider({ hasWriteAccess: true });

      const name = await getName();

      await waitFor(() => expect(name).toHaveValue('Anthropic production'));
      expect(name).toBeEnabled();
      expect(await getInput(descriptionTestId)).toBeEnabled();

      // the type of a stored provider is fixed, the other fields are editable
      for (const label of customFields.filter((field) => field !== labelType)) {
        expect(await getCustomInput(label)).toBeEnabled();
      }

      // the stored token is masked, with an action to replace it
      expect(await getApiToken()).toHaveValue('********');
      expect(screen.getByTestId(`${labelApiToken}-clear`)).toBeVisible();

      for (const action of providerActions) {
        expect(screen.getByTestId(action)).toBeInTheDocument();
      }
      expect(getSaveButton()).toBeInTheDocument();
    });

    it('opens a read-only form without the AI provider write permission', async () => {
      await openProvider({ hasWriteAccess: false });

      // the provider is still loaded and shown: read-only, not empty
      const name = await getName();

      await waitFor(() => expect(name).toHaveValue('Anthropic production'));
      expect(name).toBeDisabled();
      expect(await getInput(descriptionTestId)).toBeDisabled();
      expect(
        within(await screen.findByTestId(isActivatedTestId)).getByRole(
          'checkbox'
        )
      ).toBeDisabled();

      for (const label of customFields) {
        expect(await getCustomInput(label)).toBeDisabled();
      }

      expect(await getApiToken()).toBeDisabled();
      expect(
        screen.queryByTestId(`${labelApiToken}-clear`)
      ).not.toBeInTheDocument();

      // these actions call the provider, which requires the write permission
      for (const action of providerActions) {
        expect(screen.queryByTestId(action)).not.toBeInTheDocument();
      }
      expect(getSaveButton()).not.toBeInTheDocument();
    });
  });
};
