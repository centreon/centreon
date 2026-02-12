import {
  aclAtom,
  isOnPublicPageAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { has } from 'ramda';
import { ReactElement, useMemo } from 'react';

import { OpenTicketContext, ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';
import { WidgetProvider } from './WidgetContext';

const Widget = (props: ResourcesTableProps): ReactElement => {
  const acl = useAtomValue(aclAtom);
  const platform = useAtomValue(platformVersionsAtom);
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

  console.log('Widget render', acl);
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
      acl={acl}
      isOnPublicPage={isOnPublicPage}
      openTicketContext={openTicketContext}
      platform={platform}
    >
      <ResourcesTable {...props} openTicketContext={openTicketContext} />
    </WidgetProvider>
  );
};

export default Widget;
