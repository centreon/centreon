import { buildListingEndpoint } from '@centreon/ui';
import type { ListingParameters } from '@centreon/ui';

// import { baseEndpoint } from '../../../api/endpoints';

// export const listPlaylistsEndpoint = `${baseEndpoint}/configuration/dashboards/playlists`;

export const listPlaylistsEndpoint =
  'http://localhost:3000/api/latest/configuration/dashboards/playlists';

const buildlistPlaylistsEndpoint = (parameters: ListingParameters): string => {
  return buildListingEndpoint({
    baseEndpoint: listPlaylistsEndpoint,
    parameters
  });
};

export default buildlistPlaylistsEndpoint;
