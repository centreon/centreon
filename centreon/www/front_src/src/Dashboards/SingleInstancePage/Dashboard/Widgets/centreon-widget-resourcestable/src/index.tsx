import { ReactElement } from 'react';
import { createStore, Provider as ResourcesTableProvider } from 'jotai';

import ResourcesTable from './ResourcesTable';
import { OpenTicketContext, ResourcesTableProps } from './models';
import { openTicketAtom } from './atom';

const Widget = (props: ResourcesTableProps): ReactElement => {
  const openTicketContext: OpenTicketContext = {
    displayResources: props.panelOptions.displayResources,
    enableHostTicketCreation: props.panelOptions.enableHostTicketCreation,
    enableServiceTicketCreation: props.panelOptions.enableServiceTicketCreation,
    isDownHostHidden: props.panelOptions.isDownHostHidden,
    isOpenTicketEnabled: props.panelOptions.isOpenTicketEnabled,
    isUnreachableHostHidden: props.panelOptions.isUnreachableHostHidden,
    provider: props.panelOptions.provider
  }
  const store = createStore();
  store.set(openTicketAtom, openTicketContext);

  return (
    <ResourcesTableProvider store={store}>
      <ResourcesTable {...props} />
    </ResourcesTableProvider>
  );
}

export default Widget;
