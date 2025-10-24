import { atom } from 'jotai';

import { Resource, Ticket } from './Listing/models';
import { OpenTicketContext } from './models';

export const resourcesToAcknowledgeAtom = atom<Array<Resource>>([]);
export const resourcesToSetDowntimeAtom = atom<Array<Resource>>([]);
export const resourcesToOpenTicketAtom = atom<Array<Ticket>>([]);
export const resourcesToCloseTicketAtom = atom<Array<Ticket>>([]);
export const selectedResourcesAtom = atom<Array<Resource>>([]);

export const openTicketAtom = atom<OpenTicketContext>({
  displayResources: 'withoutTicket',
  enableHostTicketCreation: false,
  enableServiceTicketCreation: false,
  isDownHostHidden: false,
  isOpenTicketEnabled: false,
  isUnreachableHostHidden: false,
  provider: undefined
});
