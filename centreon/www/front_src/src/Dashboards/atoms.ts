import { atom } from 'jotai';

import { Dashboard } from './api/models';

export const isSharesOpenAtom = atom<Dashboard | null>(null);
