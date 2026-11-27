import { platformVersionsAtom } from '@centreon/ui-context';

import { getRequestCalls, waitForRequest } from '@test-setup/api';
import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import navigationAtom from '../../../Navigation/navigationAtoms';
import {
  labelUnexpectedResponse,
  labelUpdate,
  labelUpdateAll,
  labelUpdateSucceeded
} from '../../translatedLabels';
import initialize, { sentActions } from './initialize';
import { outdatedModuleIds } from './utils';

const [bam, map] = outdatedModuleIds;

const findUpdateAllButton = (): Promise<HTMLElement> =>
  screen.findByRole('button', { name: labelUpdateAll });

type Store = ReturnType<typeof initialize>['store'];

const renderOutdatedModules = async (
  props: Parameters<typeof initialize>[0] = {}
): Promise<{ button: HTMLElement; store: Store; unmount: () => void }> => {
  const { store, unmount } = initialize(props);
  await waitForRequest('@getExtensions');
  const button = await findUpdateAllButton();
  await waitFor(() => expect(button).toBeEnabled());

  return { button, store, unmount };
};

// Both reloads are awaited: pending at unmount, they would be cancelled.
const waitForReloads = async (store: Store): Promise<void> => {
  await waitForRequest('@getExtensions');
  await waitFor(() => {
    expect(store.get(navigationAtom)).not.toBeNull();
    expect(store.get(platformVersionsAtom)).not.toBeNull();
  });
  expect(screen.queryAllByRole('progressbar')).toHaveLength(0);
};

export default () => {
  describe('Update all: ', () => {
    it('updates each module without clearing the cache, then clears it once and refreshes the list', async () => {
      const { button, store } = await renderOutdatedModules();

      await userEvent.click(button);
      await waitForReloads(store);

      expect(sentActions).toEqual([
        'list',
        `update:${bam}:noClearCache`,
        `update:${map}:noClearCache`,
        'clearCache',
        'list'
      ]);
    });

    it('disables the button while the batch runs, so a second click sends nothing', async () => {
      const { button, store } = await renderOutdatedModules({
        updateDelay: 300
      });

      await userEvent.dblClick(button);

      await waitFor(() => expect(button).toBeDisabled());
      expect(screen.getAllByRole('progressbar')).toHaveLength(2);

      await waitForReloads(store);
      expect(button).toBeEnabled();

      expect(getRequestCalls('@postExtensionAction')).toHaveLength(3);
      expect(sentActions).toEqual([
        'list',
        `update:${bam}:noClearCache`,
        `update:${map}:noClearCache`,
        'clearCache',
        'list'
      ]);
    });

    it('carries on after a failed update and resets the spinners', async () => {
      const { button, store } = await renderOutdatedModules({
        failingUpdateId: bam
      });

      await userEvent.click(button);
      await waitForReloads(store);

      expect(sentActions).toEqual([
        'list',
        `update:${bam}:noClearCache`,
        `update:${map}:noClearCache`,
        'clearCache',
        'list'
      ]);
      expect(button).toBeEnabled();
      expect(await screen.findByText('Update failed')).toBeVisible();
      expect(screen.getAllByText('Update failed')).toHaveLength(1);
    });

    it('sends the remaining updates and the cache clear once the page is left', async () => {
      const { button, unmount } = await renderOutdatedModules({
        updateDelay: 300
      });

      await userEvent.click(button);
      await waitFor(() =>
        expect(sentActions).toContain(`update:${bam}:noClearCache`)
      );
      unmount();

      await waitFor(() =>
        expect(sentActions).toEqual([
          'list',
          `update:${bam}:noClearCache`,
          `update:${map}:noClearCache`,
          'clearCache'
        ])
      );
    });

    it('reports the update and the cache clear the server refused', async () => {
      const { button, store } = await renderOutdatedModules({
        isClearCacheRefused: true,
        refusedUpdateId: bam
      });

      await userEvent.click(button);
      await waitForReloads(store);

      expect(await screen.findByText('Update refused')).toBeVisible();
      expect(await screen.findByText('Cache clear refused')).toBeVisible();
      expect(screen.getAllByText(labelUpdateSucceeded)).toHaveLength(1);
    });

    it('reports an update whose response cannot be read', async () => {
      const { button, store } = await renderOutdatedModules({
        brokenUpdateId: bam
      });

      await userEvent.click(button);
      await waitForReloads(store);

      expect(await screen.findByText(labelUnexpectedResponse)).toBeVisible();
      expect(screen.getAllByText(labelUpdateSucceeded)).toHaveLength(1);
    });

    it('resets the spinners when the list refresh fails', async () => {
      const { button, store } = await renderOutdatedModules({
        isRefreshFailing: true
      });

      await userEvent.click(button);
      await waitForReloads(store);

      expect(button).toBeEnabled();
    });
  });

  describe('Update one: ', () => {
    const updateFirstModule = async (
      props: Parameters<typeof initialize>[0]
    ): Promise<void> => {
      const { store } = await renderOutdatedModules(props);

      const [updateIcon] = screen.getAllByLabelText(labelUpdate);
      await userEvent.click(updateIcon);
      await waitForReloads(store);
    };

    it('refreshes the list, resets the spinner and reloads the navigation after a failed update', async () => {
      await updateFirstModule({ failingUpdateId: bam });

      expect(sentActions).toEqual(['list', `update:${bam}`, 'list']);
    });

    it('disables "Update all" until the update ends', async () => {
      const { button, store } = await renderOutdatedModules({
        updateDelay: 300
      });

      const [updateIcon] = screen.getAllByLabelText(labelUpdate);
      await userEvent.click(updateIcon);

      await waitFor(() => expect(button).toBeDisabled());
      await waitForReloads(store);
      expect(button).toBeEnabled();
    });

    it('resets the spinner and reloads the navigation when the list refresh fails', async () => {
      await updateFirstModule({ isRefreshFailing: true });

      expect(sentActions).toEqual(['list', `update:${bam}`, 'list']);
    });
  });
};
