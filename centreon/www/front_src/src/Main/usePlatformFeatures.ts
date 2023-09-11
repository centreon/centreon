import { useEffect } from 'react';

import { useSetAtom } from 'jotai';

import { getData, useRequest } from '@centreon/ui';

import { platformFeaturesEndpoint } from '../api/endpoint';
import { PlatformFeatures } from '../api/models';
import { platformFeaturesDecoder } from '../api/decoders';

import { platformFeaturesAtom } from './atoms/platformFeaturesAtom';

const usePlatformFeatures = (): void => {
  const { sendRequest: sendPlatformFeatures } = useRequest<PlatformFeatures>({
    decoder: platformFeaturesDecoder,
    request: getData
  });

  const setPlatformFeatures = useSetAtom(platformFeaturesAtom);

  useEffect(() => {
    sendPlatformFeatures({ endpoint: platformFeaturesEndpoint }).then(
      setPlatformFeatures
    );
  }, []);
};

export default usePlatformFeatures;
