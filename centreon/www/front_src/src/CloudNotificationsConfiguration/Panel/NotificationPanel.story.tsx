import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useEffect, useMemo } from 'react';

import { DeleteConfirmationDialog } from '../Actions/Delete';
import { DuplicationForm } from '../Actions/Duplicate';
import { notificationsNamesAtom, panelWidthStorageAtom } from '../atom';
import Form from '.';
import { editedNotificationIdAtom, panelModeAtom } from './atom';
import { PanelMode } from './models';
import {
  emailBodyText,
  formData,
  getNotificationResponse,
  hostGroupsResponse,
  platformVersions,
  serviceGroupsResponse,
  timePeriodsResponse,
  usersResponse
} from './specs/testUtils';
import { defaultEmailSubject } from './utils';

interface Props {
  isBamModuleInstalled?: boolean;
}

const usePanelStore = (
  mode: PanelMode,
  isBamModuleInstalled: boolean
): ReturnType<typeof createStore> =>
  useMemo(() => {
    const created = createStore();
    created.set(panelWidthStorageAtom, 800);
    created.set(panelModeAtom, mode);
    if (mode === PanelMode.Edit) {
      created.set(editedNotificationIdAtom, 1);
      created.set(notificationsNamesAtom, [
        { id: 1, name: 'Notifications 1' },
        { id: 2, name: 'Notifications 2' }
      ]);
    }
    if (isBamModuleInstalled) {
      created.set(platformVersionsAtom, platformVersions as never);
    }

    return created;
  }, [mode, isBamModuleInstalled]);

const Panel = ({
  isBamModuleInstalled = false,
  mode
}: Props & { mode: PanelMode }): JSX.Element => {
  const store = usePanelStore(mode, isBamModuleInstalled);

  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <>
              <Form marginBottom={0} />
              <DeleteConfirmationDialog />
              <DuplicationForm />
            </>
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

export const Create = (props: Props): JSX.Element => (
  <Panel {...props} mode={PanelMode.Create} />
);

export const Edit = (props: Props): JSX.Element => (
  <Panel {...props} mode={PanelMode.Edit} />
);

const data = {
  defaultEmailSubject,
  emailBodyText,
  formData,
  hostGroupsResponse,
  notification: getNotificationResponse({ isBamModuleInstalled: false }),
  notificationWithBam: getNotificationResponse({ isBamModuleInstalled: true }),
  serviceGroupsResponse,
  timePeriodsResponse,
  usersResponse
};

declare global {
  interface Window {
    notificationPanelData?: typeof data;
  }
}

// Data story: the test data module reaches @centreon/ui and SVG icons, which
// Node cannot load, so specs read it from the browser.
export const Data = (): JSX.Element => {
  useEffect(() => {
    window.notificationPanelData = data;
  }, []);

  return <p>notification panel data ready</p>;
};
