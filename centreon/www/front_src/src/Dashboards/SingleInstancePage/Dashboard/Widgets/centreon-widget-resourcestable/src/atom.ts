import type { PlatformVersions } from '@centreon/ui-context';

import { atom } from 'jotai';

import { Resource, Ticket } from './Listing/models';
import { OpenTicketContext } from './models';

/**
 * Widget-scoped atoms for managing resource table state.
 * These atoms are scoped to each widget instance via WidgetProvider,
 * preventing state conflicts when multiple resource table widgets
 * are displayed on the same dashboard.
 */
export const resourcesToAcknowledgeAtom = atom<Array<Resource>>([]);
export const resourcesToSetDowntimeAtom = atom<Array<Resource>>([]);
export const resourcesToOpenTicketAtom = atom<Array<Ticket>>([]);
export const resourcesToCloseTicketAtom = atom<Array<Ticket>>([]);
export const selectedResourcesAtom = atom<Array<Resource>>([]);

/**
 * Local scoped copies of global values.
 * These are initialized in WidgetProvider with values from global Jotai atoms,
 * allowing all components to use a consistent Jotai pattern instead of mixing
 * React Context and Jotai.
 */
export const isOnPublicPageLocalAtom = atom(false);
export const platformLocalAtom = atom<PlatformVersions | null>(null);
export const openTicketContextAtom = atom<OpenTicketContext>({
  displayResources: 'withoutTicket',
  enableHostTicketCreation: false,
  enableServiceTicketCreation: false,
  isDownHostHidden: false,
  isOpenTicketEnabled: false,
  isOpenTicketInstalled: false,
  isUnreachableHostHidden: false,
  provider: undefined
});
