import { useCallback } from 'react';

import { useSetAtom } from 'jotai';

import { getData, useRequest } from '@centreon/ui';

import { platformFeaturesEndpoint } from '../api/endpoint';
import { PlatformFeatures } from '../api/models';
import { platformFeaturesDecoder } from '../api/decoders';

import { platformFeaturesAtom } from './atoms/platformFeaturesAtom';

interface UsePlatformFeaturesState {
  getPlatformFeatures: () => void;
}

const usePlatformFeatures = (): UsePlatformFeaturesState => {
  const { sendRequest: sendPlatformFeatures } = useRequest<PlatformFeatures>({
    decoder: platformFeaturesDecoder,
    request: getData
  });

  const setPlatformFeatures = useSetAtom(platformFeaturesAtom);

  const getPlatformFeatures = useCallback((): void => {
    sendPlatformFeatures({ endpoint: platformFeaturesEndpoint }).then(
      setPlatformFeatures
    );
  }, []);

  return {
    getPlatformFeatures
  };
};

export default usePlatformFeatures;
