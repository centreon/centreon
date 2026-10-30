import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { equals } from 'ramda';

import {
  labelAlias,
  labelApplyResourceAccessRule,
  labelComments,
  labelGeographicCoordinates,
  labelIcon,
  labelInvalidCoordinateFormat,
  labelName,
  labelSelectHosts
} from '../translatedLabels';
import initialize from './initialize';
import { getGroups, getPayload } from './utils';

const platforms = ['OnPrem', 'Cloud'];

// `@centreon/ui` text fields put the test id on the wrapper and the input.
const getInput = async (testId: string): Promise<HTMLElement> =>
  (await screen.findAllByTestId(testId))[1];

const getSuffixedInput = (label: string): HTMLElement => {
  const input = document.querySelector<HTMLElement>(
    `[data-testid-suffix="test-${label}"] input`
  );

  if (!input) {
    throw new Error(`No input suffixed "test-${label}"`);
  }

  return input;
};

const getSubmit = (): HTMLElement => {
  const submit = document.querySelector<HTMLElement>(
    'button[data-testid="submit"]'
  );

  if (!submit) {
    throw new Error('No submit button');
  }

  return submit;
};

const selectOption = async (name: string | RegExp): Promise<void> => {
  await userEvent.click(await screen.findByRole('option', { name }));
};

export default () => {
  describe('Modal', () => {
    platforms.forEach((platform) => {
      const isCloudPlatform = equals(platform, 'Cloud');

      it('displays the modal in view mode when the user does not have write access', async () => {
        initialize({ hasWriteAccess: false, isCloudPlatform });

        await waitForRequest('@getAllHostGroups');

        await userEvent.click(await screen.findByText('host group 1'));

        await waitForRequest('@getHostGroupDetails');

        expect(await screen.findByText('View a host group')).toBeVisible();

        // Every field filled in from the API and read-only, the access rules on
        // a cloud platform only.
        const comments = await getInput(labelComments);

        await waitFor(() =>
          expect(comments).toHaveValue(getPayload({}).comment)
        );
        expect(comments).toBeDisabled();
        expect(await getInput(labelName)).toHaveValue(getPayload({}).name);
        expect(await getInput(labelName)).toBeDisabled();
        expect(await getInput(labelGeographicCoordinates)).toBeDisabled();
        expect(screen.getByText('host 3')).toBeVisible();
        if (isCloudPlatform) {
          expect(screen.getByText('rule 2')).toBeVisible();
        } else {
          expect(screen.queryByText('rule 2')).not.toBeInTheDocument();
        }

        await userEvent.click(screen.getByLabelText('close'));
      });
    });

    platforms.forEach((platform) => {
      describe(platform, () => {
        const isCloudPlatform = equals(platform, 'Cloud');

        beforeEach(() => {
          initialize({ isCloudPlatform });
        });

        it('shows form fields organized into groups, with each field initialized with default values', async () => {
          await waitForRequest('@getAllHostGroups');

          await userEvent.click(await screen.findByTestId('add-resource'));

          expect(await screen.findByText('Add a host group')).toBeVisible();

          const modal = await screen.findByTestId('Modal');

          for (const { name } of getGroups({ isCloudPlatform })) {
            expect(within(modal).getAllByText(name)[0]).toBeVisible();
          }

          expect(await getInput(labelName)).toHaveValue('');
          expect(await getInput(labelAlias)).toHaveValue('');
          expect(screen.getByTestId(labelSelectHosts)).toHaveValue('');

          if (isCloudPlatform) {
            expect(
              screen.getByTestId(labelApplyResourceAccessRule)
            ).toHaveValue('');
          } else {
            expect(
              screen.queryByTestId(labelApplyResourceAccessRule)
            ).not.toBeInTheDocument();
          }

          expect(await getInput(labelGeographicCoordinates)).toHaveValue('');
          expect(await getInput(labelComments)).toHaveValue('');

          // The groups as tabs on a cloud platform only, and the fields
          // editable.
          if (isCloudPlatform) {
            expect(
              screen.getByTestId('tab-General information')
            ).toBeInTheDocument();
          } else {
            expect(
              screen.queryByTestId('tab-General information')
            ).not.toBeInTheDocument();
          }
          expect(await getInput(labelName)).toBeEnabled();
          expect(getSubmit()).toBeDisabled();

          await userEvent.click(screen.getByLabelText('close'));
        });

        it('shows form fields organized into groups, with each field initialized with the value received from the API', async () => {
          await waitForRequest('@getAllHostGroups');

          await userEvent.click(await screen.findByText('host group 1'));

          await waitForRequest('@getHostGroupDetails');

          expect(await screen.findByText('Modify a host group')).toBeVisible();

          const modal = await screen.findByTestId('Modal');

          for (const { name } of getGroups({ isCloudPlatform })) {
            expect(within(modal).getAllByText(name)[0]).toBeVisible();
          }

          const nameInput = await getInput(labelName);

          await waitFor(() =>
            expect(nameInput).toHaveValue(getPayload({}).name)
          );
          expect(await getInput(labelAlias)).toHaveValue(getPayload({}).alias);

          expect(await screen.findByText('host 1')).toBeVisible();
          expect(screen.getByText('host 2')).toBeVisible();
          expect(screen.getByText('host 3')).toBeVisible();

          if (isCloudPlatform) {
            expect(
              screen.getByTestId(labelApplyResourceAccessRule)
            ).toBeVisible();

            expect(screen.getByText('rule 1')).toBeVisible();
            expect(screen.getByText('rule 2')).toBeVisible();
          } else {
            expect(
              screen.queryByTestId(labelApplyResourceAccessRule)
            ).not.toBeInTheDocument();
          }

          expect(await getInput(labelGeographicCoordinates)).toHaveValue(
            getPayload({}).geo_coords
          );
          expect(await getInput(labelComments)).toHaveValue(
            getPayload({}).comment
          );

          // The icon selected, with its preview, and the fields editable.
          expect(screen.getByTestId(labelIcon)).toHaveValue('cypress_logo');
          expect(
            within(modal).getByRole('img', { name: 'cypress_logo' })
          ).toBeInTheDocument();
          expect(nameInput).toBeEnabled();

          await userEvent.click(screen.getByLabelText('close'));
        });

        it('sends a POST request when the Create Button is clicked', async () => {
          await waitForRequest('@getAllHostGroups');

          await userEvent.click(await screen.findByTestId('add-resource'));
          await screen.findByText('Add a host group');

          const nameInput = getSuffixedInput(labelName);

          await userEvent.clear(nameInput);
          await userEvent.type(nameInput, getPayload({}).name);

          const aliasInput = getSuffixedInput(labelAlias);

          await userEvent.clear(aliasInput);
          await userEvent.type(aliasInput, getPayload({}).alias);

          const comments = await getInput(labelComments);

          await userEvent.clear(comments);
          await userEvent.type(
            comments,
            getPayload({ isCloudPlatform }).comment
          );

          const coordinates = await getInput(labelGeographicCoordinates);

          await userEvent.clear(coordinates);
          await userEvent.type(
            coordinates,
            getPayload({ isCloudPlatform }).geo_coords
          );

          await userEvent.click(screen.getByTestId(labelSelectHosts));

          await waitForRequest('@getHosts');
          await selectOption('host 1');
          await selectOption('host 2');
          await selectOption('host 3');

          await userEvent.click(screen.getByTestId('Modal-header'));

          if (isCloudPlatform) {
            await userEvent.click(
              screen.getByTestId(labelApplyResourceAccessRule)
            );

            await waitForRequest('@getAccessRules');

            await selectOption('rule 1');
            await selectOption('rule 2');

            await userEvent.click(screen.getByTestId('Modal-header'));
          }

          await userEvent.click(screen.getByTestId(labelIcon));
          await waitForRequest('@getImagesList');
          // The option's name also holds its thumbnail's.
          await selectOption(/^cypress_logo/);

          await waitFor(() => expect(getSubmit()).toBeEnabled());
          await userEvent.click(getSubmit());

          const { request } = await waitForRequest('@createHostGroup');

          expect(request.body).toEqual(getPayload({ isCloudPlatform }));

          // The modal closed and the success message.
          expect(await screen.findByText('Host group created')).toBeVisible();
          await waitFor(() =>
            expect(screen.queryByTestId('Modal')).not.toBeInTheDocument()
          );
        });

        it('sends an UPDATE request when the Update Button is clicked', async () => {
          await waitForRequest('@getAllHostGroups');

          await userEvent.click(await screen.findByText('host group 1'));

          await waitForRequest('@getHostGroupDetails');

          const nameInput = await getInput(labelName);

          await waitFor(() =>
            expect(nameInput).toHaveValue(getPayload({}).name)
          );

          await userEvent.clear(getSuffixedInput(labelName));
          await userEvent.type(getSuffixedInput(labelName), 'Updated name');

          await waitFor(() => expect(getSubmit()).toBeEnabled());
          await userEvent.click(getSubmit());

          const { request } = await waitForRequest('@updateHostGroup');

          expect(request.body).toEqual({
            ...getPayload({ isCloudPlatform }),
            name: 'Updated name'
          });

          expect(await screen.findByText('Host group updated')).toBeVisible();

          // The modal closed.
          await waitFor(() =>
            expect(screen.queryByTestId('Modal')).not.toBeInTheDocument()
          );
        });
      });
    });

    it('validate geographic coordianates', async () => {
      initialize();

      await waitForRequest('@getAllHostGroups');

      await userEvent.click(await screen.findByTestId('add-resource'));

      const nameInput = await getInput(labelName);

      await userEvent.clear(nameInput);
      await userEvent.type(nameInput, 'name');

      const coordinates = await getInput(labelGeographicCoordinates);

      await userEvent.clear(coordinates);
      await userEvent.type(coordinates, '123');
      await userEvent.click(screen.getByTestId('Modal-header'));

      expect(
        await screen.findByText(labelInvalidCoordinateFormat)
      ).toBeVisible();
      expect(getSubmit()).toBeDisabled();

      // The error flags the coordinates field.
      expect(coordinates).toHaveAttribute('aria-invalid', 'true');
      expect(nameInput).toHaveAttribute('aria-invalid', 'false');

      await userEvent.clear(coordinates);
      await userEvent.type(coordinates, '-40.12,22.44');

      await waitFor(() => expect(getSubmit()).toBeEnabled());
      expect(
        screen.queryByText(labelInvalidCoordinateFormat)
      ).not.toBeInTheDocument();

      // The field no longer in error.
      expect(coordinates).toHaveAttribute('aria-invalid', 'false');

      await userEvent.click(screen.getByLabelText('close'));
      await userEvent.click(await screen.findByLabelText('Discard'));

      await waitFor(() =>
        expect(screen.queryByTestId('Modal')).not.toBeInTheDocument()
      );
    });

    it('disables the save button when the platform is cloud and Resource Access Rules field is empty', async () => {
      initialize({ hasWriteAccess: true, isCloudPlatform: true });

      await waitForRequest('@getAllHostGroups');

      await userEvent.click(await screen.findByTestId('add-resource'));

      const nameInput = await getInput(labelName);

      await userEvent.clear(nameInput);

      await userEvent.click(screen.getByTestId('tab-General information'));

      await waitFor(() =>
        expect(nameInput).toHaveAttribute('aria-invalid', 'true')
      );

      await userEvent.type(nameInput, 'name');

      // The validation pass that clears the name's error leaves the access
      // rules required.
      await waitFor(() =>
        expect(nameInput).toHaveAttribute('aria-invalid', 'false')
      );
      expect(nameInput).toHaveValue('name');
      expect(getSubmit()).toBeDisabled();

      await userEvent.click(screen.getByTestId(labelApplyResourceAccessRule));

      await waitForRequest('@getAccessRules');

      await selectOption('rule 1');

      await waitFor(() => expect(getSubmit()).toBeEnabled());

      // The rule selected in the list.
      expect(screen.getByRole('option', { name: 'rule 1' })).toHaveAttribute(
        'aria-selected',
        'true'
      );

      await userEvent.click(screen.getByLabelText('close'));
      await userEvent.click(await screen.findByLabelText('Discard'));

      await waitFor(() =>
        expect(screen.queryByTestId('Modal')).not.toBeInTheDocument()
      );
    });
  });
};
