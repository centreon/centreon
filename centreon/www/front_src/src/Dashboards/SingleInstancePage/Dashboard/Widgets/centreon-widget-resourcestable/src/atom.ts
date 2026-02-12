import { atom } from 'jotai';

import { Resource, Ticket } from './Listing/models';

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
