import { SnackbarProvider, TestQueryProvider } from '@centreon/ui';
import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';

import { createStore, Provider, useAtomValue } from 'jotai';
import { useMemo } from 'react';

import { selectedColumnIdsAtom } from '../Listing/listingAtoms';
import Actions from '.';
import { selectedResourcesAtom } from './actionsAtoms';

const resources = {
  anomalyDetection: {
    has_passive_checks_enabled: true,
    id: 0,
    parent: { id: 1, name: 'Host' },
    type: 'anomaly-detection'
  },
  host: { has_passive_checks_enabled: true, id: 0, parent: null, type: 'host' },
  service: {
    has_passive_checks_enabled: true,
    id: 0,
    parent: { id: 1, name: 'Host' },
    type: 'service'
  }
};

const acl = (canPerformActions: boolean): object => {
  const actions = {
    acknowledgement: canPerformActions,
    check: canPerformActions,
    comment: canPerformActions,
    disacknowledgement: canPerformActions,
    downtime: canPerformActions,
    forced_check: canPerformActions,
    submit_status: canPerformActions
  };

  return { actions: { host: actions, service: actions } };
};

// Records the selection, which some actions clear once sent.
const SelectionRecorder = (): JSX.Element => {
  const selected = useAtomValue(selectedResourcesAtom);

  return (
    <form hidden>
      <input data-testid="selected-count" readOnly value={selected.length} />
    </form>
  );
};

interface Props {
  canPerformActions?: boolean;
  selected?: keyof typeof resources;
  selectedColumnIds?: Array<string>;
}

export const Default = ({
  canPerformActions = true,
  selected,
  selectedColumnIds
}: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();
    created.set(userAtom, {
      alias: 'admin',
      isExportButtonEnabled: true,
      locale: 'en',
      timezone: 'Europe/Paris'
    } as never);
    created.set(refreshIntervalAtom, 15);
    created.set(downtimeAtom, {
      duration: 7200,
      fixed: true,
      with_services: false
    } as never);
    created.set(aclAtom, acl(canPerformActions) as never);
    created.set(acknowledgementAtom, {
      force_active_checks: false,
      notify: false,
      persistent: true,
      sticky: true,
      with_services: true
    } as never);
    if (selected) {
      created.set(selectedResourcesAtom, [resources[selected]] as never);
    }
    if (selectedColumnIds) {
      created.set(selectedColumnIdsAtom, selectedColumnIds);
    }

    return created;
  }, [canPerformActions, selected, selectedColumnIds]);

  return (
    <SnackbarProvider>
      <Provider store={store}>
        <TestQueryProvider>
          <>
            <Actions onRefresh={() => undefined} />
            <SelectionRecorder />
          </>
        </TestQueryProvider>
      </Provider>
    </SnackbarProvider>
  );
};
