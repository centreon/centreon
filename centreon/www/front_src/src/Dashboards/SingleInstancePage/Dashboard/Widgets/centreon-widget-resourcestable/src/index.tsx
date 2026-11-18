import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  isOnPublicPageAtom,
  platformVersionsAtom,
  userAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { has } from 'ramda';
import { type ReactElement, useMemo } from 'react';

import type { OpenTicketContext, ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';
import { WidgetProvider } from './WidgetContext';

const Widget = (props: ResourcesTableProps): ReactElement => {
  const acl = useAtomValue(aclAtom);
  const platform = useAtomValue(platformVersionsAtom);
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);
  const acknowledgement = useAtomValue(acknowledgementAtom);
  const downtime = useAtomValue(downtimeAtom);
  const user = useAtomValue(userAtom);

  const openTicketContext = useMemo(
    (): OpenTicketContext => ({
      displayResources: props.panelOptions.displayResources,
      enableHostTicketCreation: props.panelOptions.enableHostTicketCreation,
      enableServiceTicketCreation:
        props.panelOptions.enableServiceTicketCreation,
      isDownHostHidden: props.panelOptions.isDownHostHidden,
      isOpenTicketEnabled: props.panelOptions.isOpenTicketEnabled,
      isOpenTicketInstalled: has('centreon-open-tickets', platform?.modules),
      isUnreachableHostHidden: props.panelOptions.isUnreachableHostHidden,
      provider: props.panelOptions.provider
    }),
    [
      props.panelOptions.displayResources,
      props.panelOptions.enableHostTicketCreation,
      props.panelOptions.enableServiceTicketCreation,
      props.panelOptions.isDownHostHidden,
      props.panelOptions.isOpenTicketEnabled,
      props.panelOptions.isUnreachableHostHidden,
      props.panelOptions.provider,
      platform?.modules
    ]
  );

  return (
    <WidgetProvider
      acknowledgement={acknowledgement}
      acl={acl}
      downtime={downtime}
      isOnPublicPage={isOnPublicPage}
      openTicketContext={openTicketContext}
      platform={platform}
      user={user}
    >
      <ResourcesTable {...props} />
    </WidgetProvider>
  );
};

export default Widget;
