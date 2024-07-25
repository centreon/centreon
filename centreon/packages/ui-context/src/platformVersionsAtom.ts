import { atom } from 'jotai';

import { PlatformVersions } from './types';

export const platformVersionsAtom = atom<PlatformVersions | null>(null);
