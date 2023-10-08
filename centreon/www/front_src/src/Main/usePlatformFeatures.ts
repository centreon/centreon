import { useCallback } from 'react';

import { useSetAtom } from 'jotai';

import { getData, useRequest } from '@centreon/ui';
import { platformFeaturesAtom, PlatformFeatures } from '@centreon/ui-context';

import { platformFeaturesEndpoint } from '../api/endpoint';
import { platformFeaturesDecoder } from '../api/decoders';

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
