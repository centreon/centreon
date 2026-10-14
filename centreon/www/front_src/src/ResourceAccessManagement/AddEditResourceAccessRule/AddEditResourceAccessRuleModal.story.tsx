import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import { platformVersionsAtom } from '@centreon/ui-context';

import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import {
  editedResourceAccessRuleIdAtom,
  modalStateAtom,
  resourceAccessRulesNamesAtom
} from '../atom';
import { ModalMode } from '../models';
import { AddEditResourceAccessRuleModal } from '.';
import { platformVersions } from './specs/testUtils';

interface Props {
  isBamModuleInstalled?: boolean;
}

const Modal = ({
  isBamModuleInstalled = false,
  mode
}: Props & { mode: ModalMode }): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(modalStateAtom, { isOpen: true, mode });
    if (mode === ModalMode.Edit) {
      created.set(editedResourceAccessRuleIdAtom, 1);
      created.set(resourceAccessRulesNamesAtom, [
        { id: 1, name: 'Rule 1' },
        { id: 2, name: 'Rule 2' }
      ]);
    }
    if (isBamModuleInstalled) {
      created.set(platformVersionsAtom, platformVersions as never);
    }

    return created;
  }, [mode, isBamModuleInstalled]);

  return (
    <div style={{ height: '100vh' }}>
      <Provider store={store}>
        <TestQueryProvider>
          <SnackbarProvider>
            <AddEditResourceAccessRuleModal />
          </SnackbarProvider>
        </TestQueryProvider>
      </Provider>
    </div>
  );
};

export const Create = (props: Props): JSX.Element => (
  <Modal {...props} mode={ModalMode.Create} />
);

export const Edit = (props: Props): JSX.Element => (
  <Modal {...props} mode={ModalMode.Edit} />
);
