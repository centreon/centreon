import { ReactElement, useEffect } from 'react';
import { createStore, Provider as ResourcesTableProvider, useAtom, useAtomValue, useSetAtom } from 'jotai';

import ResourcesTable from './ResourcesTable';
import { OpenTicketContext, ResourcesTableProps } from './models';
import { openTicketAtom } from './atom';
import { platformVersionsAtom } from 'packages/ui-context/src';
import { has } from 'ramda';

const Widget = (props: ResourcesTableProps): ReactElement => {
  const platform = useAtomValue(platformVersionsAtom);

  const openTicketContext: OpenTicketContext = {
    displayResources: props.panelOptions.displayResources,
    enableHostTicketCreation: props.panelOptions.enableHostTicketCreation,
    enableServiceTicketCreation: props.panelOptions.enableServiceTicketCreation,
    isDownHostHidden: props.panelOptions.isDownHostHidden,
    isOpenTicketEnabled: props.panelOptions.isOpenTicketEnabled,
    isOpenTicketInstalled: has('centreon-open-tickets', platform?.modules),
    isUnreachableHostHidden: props.panelOptions.isUnreachableHostHidden,
    provider: props.panelOptions.provider
  }

  const setOpenTicket = useSetAtom(openTicketAtom);

  useEffect(() => {
    setOpenTicket(openTicketContext);
  }, [JSON.stringify(openTicketContext)]);

  return (
      <ResourcesTable {...props} />
  );
}

export default Widget;
