import { atom } from 'jotai';

import { FeatureFlags, PlatformFeatures } from './types';

export const platformFeaturesAtom = atom<PlatformFeatures | null>(null);
export const featureFlagsDerivedAtom = atom<FeatureFlags | null>(
  (get): FeatureFlags => {
    const platformFeatures = get(platformFeaturesAtom);

    return platformFeatures?.featureFlags as FeatureFlags;
  }
);
