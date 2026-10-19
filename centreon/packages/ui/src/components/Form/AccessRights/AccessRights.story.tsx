import { createStore, Provider } from 'jotai';
import { useState } from 'react';

import { SnackbarProvider, TestQueryProvider } from '../../..';
import { AccessRights } from './AccessRights';
import {
  emptyAccessRights,
  labels,
  roles,
  simpleAccessRights
} from './storiesData';

interface Props {
  empty?: boolean;
  loading?: boolean;
}

// Counts the calls of the callbacks in `calls`.
export const Default = ({
  empty = false,
  loading = false
}: Props): JSX.Element => {
  const [calls, setCalls] = useState({ cancel: 0, change: 0, save: 0 });
  const count = (name: keyof typeof calls) => (): void =>
    setCalls((current) => ({ ...current, [name]: current[name] + 1 }));

  return (
    <SnackbarProvider>
      <>
      <TestQueryProvider>
        <Provider store={createStore()}>
          <AccessRights
            cancel={count('cancel')}
            endpoints={{
              contact: '/contacts',
              contactGroup: '/contact-groups'
            }}
            initialValues={empty ? emptyAccessRights : simpleAccessRights}
            labels={labels}
            loading={loading}
            onChange={count('change')}
            roles={roles}
            submit={async () => count('save')()}
          />
        </Provider>
      </TestQueryProvider>
      <output data-testid="calls">{JSON.stringify(calls)}</output>
      </>
    </SnackbarProvider>
  );
};
