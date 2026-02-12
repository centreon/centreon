import type { Acl, PlatformVersions } from '@centreon/ui-context';

import { atom } from 'jotai';

import { Resource, Ticket } from './Listing/models';
import { OpenTicketContext } from './models';

/**
 * Widget-scoped atoms for managing resource table state.
 * These atoms are scoped to each widget instance via WidgetProvider
 */
export const resourcesToAcknowledgeAtom = atom<Array<Resource>>([]);
export const resourcesToSetDowntimeAtom = atom<Array<Resource>>([]);
export const resourcesToOpenTicketAtom = atom<Array<Ticket>>([]);
export const resourcesToCloseTicketAtom = atom<Array<Ticket>>([]);
export const selectedResourcesAtom = atom<Array<Resource>>([]);

/**
 * Local scoped copies of global values.
 * These are initialized in WidgetProvider with values from global Jotai atoms,
 */
const defaultAcl: Acl = {
  actions: {
    host: {
      acknowledgement: false,
      check: false,
      comment: false,
      downtime: false,
      submit_status: false
    },
    service: {
      acknowledgement: false,
      check: false,
      comment: false,
      downtime: false,
      submit_status: false
    }
  }
};
export const aclLocalAtom = atom(defaultAcl);
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
