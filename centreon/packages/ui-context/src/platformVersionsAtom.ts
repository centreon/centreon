import { atom } from 'jotai';

import type { PlatformVersions } from './types';

export const platformVersionsAtom = atom<PlatformVersions | null>(null);
