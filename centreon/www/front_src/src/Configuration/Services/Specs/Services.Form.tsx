import { waitForRequest } from '@test-setup/api';
import { screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { panelDataTestIds } from '../../ConfigurationBase/Panel/dataTestIds';
import {
  labelAdvanced,
  labelCheckCommandTemplateCustomMacros,
  labelGeneralInformation,
  labelMonitoringEngineSchedulingOptions,
  labelNotification,
  labelRelations
} from '../translatedLabels';
import initialize from './initialize';

const initializeListing = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<void> => {
  initialize(props);

  await waitForRequest('@getAllServices');
};

const openRow = async (name: string): Promise<void> => {
  await userEvent.click(await screen.findByText(name));
};

const openCreationForm = async (): Promise<void> => {
  await userEvent.click(await screen.findByTestId('add-resource'));
};

const getSaveButton = (): HTMLButtonElement | null =>
  document.querySelector<HTMLButtonElement>(
    `button[data-testid="${panelDataTestIds.save}"]`
  );

const expectHeader = async (text: string): Promise<void> => {
  const header = await screen.findByTestId(panelDataTestIds.header);

  await waitFor(() =>
    expect(header).toHaveTextContent(new RegExp(`^${text}$`))
  );
};

const getSectionTabs = async (): Promise<Array<string>> => {
  const content = await screen.findByTestId(panelDataTestIds.content);
  const tabs = await within(content).findAllByRole('tab');

  return tabs.map((tab) => tab.textContent ?? '');
};

export default () => {
  it('opens the creation form in a side panel rather than a modal', async () => {
    await initializeListing();

    await openCreationForm();

    expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
    expect(screen.queryByTestId('Modal')).not.toBeInTheDocument();

    await expectHeader('Add a service');
  });

  it('opens a service from its row on the detail endpoint', async () => {
    await initializeListing();

    await openRow('cpu');

    const { request } = await waitForRequest('@getService0');

    // API Platform, not the legacy detail endpoint under `api/latest`.
    expect(request.url.pathname).toContain('/api/configuration/services/0');
    expect(request.url.pathname).not.toContain('/api/latest');

    expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
    // Named by the listing row.
    await expectHeader('cpu');
    await waitFor(() => expect(getSaveButton()).toBeInTheDocument());
  });

  it('opens a service read-only for a user who may only look at services', async () => {
    await initializeListing({ hasWriteAccess: false });

    await openRow('cpu');

    // The service is still loaded and shown: read-only, not empty.
    await waitForRequest('@getService0');

    expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
    await expectHeader('cpu');
    expect(getSaveButton()).not.toBeInTheDocument();
  });

  it('opens the service a deep link names, in edit mode', async () => {
    initialize({ searchParams: '?mode=edit&id=0' });

    const { request } = await waitForRequest('@getService0');

    expect(request.url.pathname).toContain('/api/configuration/services/0');

    expect(await screen.findByTestId(panelDataTestIds.content)).toBeVisible();
    // Named by the detail response, there being no row to take it from.
    await expectHeader('cpu');
    await waitFor(() => expect(getSaveButton()).toBeInTheDocument());
  });

  it('lists the six sections of the mockup in its order', async () => {
    await initializeListing();

    await openCreationForm();

    await waitFor(async () =>
      expect(await getSectionTabs()).toEqual([
        labelGeneralInformation,
        labelCheckCommandTemplateCustomMacros,
        labelRelations,
        labelMonitoringEngineSchedulingOptions,
        labelNotification,
        labelAdvanced
      ])
    );
  });

  it('drops the Notification section on a cloud platform', async () => {
    await initializeListing({ isCloudPlatform: true });

    await openCreationForm();

    await waitFor(async () =>
      expect(await getSectionTabs()).toEqual([
        labelGeneralInformation,
        labelCheckCommandTemplateCustomMacros,
        labelRelations,
        labelMonitoringEngineSchedulingOptions,
        labelAdvanced
      ])
    );
  });
};
