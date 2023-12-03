import { buildListingEndpoint } from '@centreon/ui';
import type { ListingParameters } from '@centreon/ui';

import { playlistsEndpoint } from '../../../../api/endpoints';

const buildlistPlaylistsEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: playlistsEndpoint,
    parameters
  });
};

export default buildlistPlaylistsEndpoint;
