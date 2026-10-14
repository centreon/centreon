import {
  platformFeaturesAtom,
  platformVersionsAtom
} from '@centreon/ui-context';

import { useHydrateAtoms } from 'jotai/utils';

import SearchHelp from './SearchHelp';

interface Props {
  isCloudPlatform?: boolean;
}

const platformVersions = {
  modules: {},
  web: { fix: '0', major: '23', minor: '10', version: '23.10.0' },
  widgets: {}
};

export const Default = ({ isCloudPlatform = false }: Props): JSX.Element => {
  useHydrateAtoms([
    [platformVersionsAtom, platformVersions],
    [platformFeaturesAtom, { featureFlags: {}, isCloudPlatform }]
  ]);

  return <SearchHelp />;
};
