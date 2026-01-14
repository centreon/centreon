import { useAtomValue, useSetAtom } from 'jotai';
import { ReactElement, useEffect, useMemo } from 'react';

import { platformVersionsAtom } from 'packages/ui-context/src';
import { has } from 'ramda';
import ResourcesTable from './ResourcesTable';
import { openTicketAtom } from './atom';
import { OpenTicketContext, ResourcesTableProps } from './models';

const Widget = (props: ResourcesTableProps): ReactElement => {
  const platform = useAtomValue(platformVersionsAtom);

  const openTicketContext = useMemo((): OpenTicketContext => ({
    displayResources: props.panelOptions.displayResources,
    enableHostTicketCreation: props.panelOptions.enableHostTicketCreation,
    enableServiceTicketCreation: props.panelOptions.enableServiceTicketCreation,
    isDownHostHidden: props.panelOptions.isDownHostHidden,
    isOpenTicketEnabled: props.panelOptions.isOpenTicketEnabled,
    isOpenTicketInstalled: has('centreon-open-tickets', platform?.modules),
    isUnreachableHostHidden: props.panelOptions.isUnreachableHostHidden,
    provider: props.panelOptions.provider
  }), [
    props.panelOptions.displayResources,
    props.panelOptions.enableHostTicketCreation,
    props.panelOptions.enableServiceTicketCreation,
    props.panelOptions.isDownHostHidden,
    props.panelOptions.isOpenTicketEnabled,
    props.panelOptions.isUnreachableHostHidden,
    props.panelOptions.provider,
    platform?.modules
  ]);

  const setOpenTicket = useSetAtom(openTicketAtom);

  useEffect(() => {
    setOpenTicket(openTicketContext);
  }, [JSON.stringify(openTicketContext), setOpenTicket]);

  return <ResourcesTable {...props} />;
};

export default Widget;
