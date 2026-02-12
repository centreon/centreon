import { isOnPublicPageAtom, platformVersionsAtom } from '@centreon/ui-context';

import { useAtomValue } from 'jotai';
import { has } from 'ramda';
import { ReactElement, useMemo } from 'react';

import { OpenTicketContext, ResourcesTableProps } from './models';
import ResourcesTable from './ResourcesTable';
import { WidgetProvider } from './WidgetContext';

/**
 * Widget wrapper that provides isolated Jotai state per widget instance.
 *
 * Each widget instance gets its own Jotai store to prevent state conflicts
 * when multiple resourcestable widgets are displayed on the same dashboard.
 *
 * Global values from ui-context (isOnPublicPage, platform) are read here
 * (outside the scoped Provider) and passed down via React Context.
 */
const Widget = (props: ResourcesTableProps): ReactElement => {
  // Read global atoms BEFORE entering the scoped Provider
  const platform = useAtomValue(platformVersionsAtom);
  const isOnPublicPage = useAtomValue(isOnPublicPageAtom);

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
      isOnPublicPage={isOnPublicPage}
      openTicketContext={openTicketContext}
      platform={platform}
    >
      <ResourcesTable {...props} openTicketContext={openTicketContext} />
    </WidgetProvider>
  );
};

export default Widget;
