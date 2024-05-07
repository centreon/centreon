import { atom } from 'jotai';

import { Dashboard, NamedEntity } from './api/models';

export const isSharesOpenAtom = atom<Dashboard | null>(null);
export const dashboardToDeleteAtom = atom<Dashboard | null>(null);
export const dashboardToDuplicateAtom = atom<Dashboard | null>(null);
export const dashboardsToExportAtom = atom<NamedEntity | null>(null);
