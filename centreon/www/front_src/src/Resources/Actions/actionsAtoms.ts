import { atom } from 'jotai';
import { atomWithStorage } from 'jotai/utils';

import { Resource, Visualization } from '../models';

export const selectedResourcesAtom = atom<Array<Resource>>([]);
export const resourcesToAcknowledgeAtom = atom<Array<Resource>>([]);
export const resourcesToSetDowntimeAtom = atom<Array<Resource>>([]);
export const resourcesToCheckAtom = atom<Array<Resource>>([]);
export const resourcesToDisacknowledgeAtom = atom<Array<Resource>>([]);
export const selectedVisualizationAtom = atomWithStorage<Visualization>(
  'centreon-resources-status-23.10-status-visualization',
  Visualization.All
);

export const isExportToCSVDialogOpenAtom = atom<boolean>(false);
