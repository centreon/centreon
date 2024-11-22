import { useCallback } from 'react';

import { useSetAtom } from 'jotai';

import { getData, useRequest } from '@centreon/ui';
import { PlatformFeatures, platformFeaturesAtom } from '@centreon/ui-context';

import { platformFeaturesDecoder } from '../api/decoders';
import { platformFeaturesEndpoint } from '../api/endpoint';

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
