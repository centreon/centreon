import { atom } from 'jotai';

import { PlatformFeatures } from '../../api/models';

export const platformFeaturesAtom = atom<PlatformFeatures | null>(null);
