import { atom } from 'jotai';

import { Resource } from './Listing/models';

export const resourcesToAcknowledgeAtom = atom<Array<Resource>>([]);
export const resourcesToSetDowntimeAtom = atom<Array<Resource>>([]);
export const selectedResourcesAtom = atom<Array<Resource>>([]);
