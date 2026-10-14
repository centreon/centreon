import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  platformFeaturesAtom,
  userPermissionsAtom
} from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';
import { MemoryRouter } from 'react-router';

import { labelAllPollers, labelConfigurePollers } from '../translatedLabels';
import { PollerSubMenu } from './PollerSubMenu';

interface Props {
  canCreatePoller?: boolean;
  /** null emulates the atom before /platform/features resolves. */
  isCloudPlatform?: boolean | null;
}

export const Default = ({
  canCreatePoller = true,
  isCloudPlatform = false
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userPermissionsAtom, {
      create_edit_poller_cfg: canCreatePoller
    } as never);
    created.set(
      platformFeaturesAtom,
      isCloudPlatform === null ? null : { featureFlags: {}, isCloudPlatform }
    );

    return created;
  }, [canCreatePoller, isCloudPlatform]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <MemoryRouter>
          <SnackbarProvider maxSnackbars={2}>
            {/* Shrink-wrapped like the popover the submenu lives in. */}
            <div className="w-fit">
              <PollerSubMenu
                allPollerLabel={labelAllPollers}
                closeSubMenu={() => undefined}
                displayPollerButton
                exportConfig={{ isExportButtonEnabled: true }}
                issues={[]}
                pollerConfig={{
                  label: labelConfigurePollers,
                  redirect: () => undefined,
                  testId: labelConfigurePollers
                }}
                pollerCount={3}
              />
            </div>
          </SnackbarProvider>
        </MemoryRouter>
      </Provider>
    </TestQueryProvider>
  );
};
