import {
  SnackbarProvider,
  TestQueryProvider,
  useFetchQuery
} from '@centreon/ui';
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
import { resourceAccessRuleDecoder } from './api/decoders';
import { resourceAccessRuleEndpoint } from './api/endpoints';
import { platformVersions } from './specs/testUtils';

interface Props {
  isBamModuleInstalled?: boolean;
}

// The listing loads the edited rule in the app: do the same so the modal
// reads it from the query cache.
const EditedRuleLoader = (): null => {
  useFetchQuery({
    decoder: resourceAccessRuleDecoder,
    getEndpoint: () => resourceAccessRuleEndpoint({ id: 1 }),
    getQueryKey: () => ['resource-access-rule', 1],
    queryOptions: { suspense: false }
  });

  return null;
};

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
            <>
              {mode === ModalMode.Edit && <EditedRuleLoader />}
              <AddEditResourceAccessRuleModal />
            </>
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
