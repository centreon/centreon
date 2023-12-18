import {
  buildListingDecoder,
  buildListingEndpoint,
  useFetchQuery
} from '@centreon/ui';

import { playlistsEndpoint } from '../../../api/endpoints';
import { namedEntityDecoder } from '../api/decoders';
import { NamedEntity } from '../../../api/models';

interface UseListPlaylistsSytate {
  playlists: Array<NamedEntity>;
}

export const useListPlaylists = (): UseListPlaylistsSytate => {
  const { data: playlists } = useFetchQuery({
    decoder: buildListingDecoder({
      entityDecoder: namedEntityDecoder,
      entityDecoderName: 'playlist',
      listingDecoderName: 'playlists'
    }),
    getEndpoint: () =>
      buildListingEndpoint({
        baseEndpoint: playlistsEndpoint,
        parameters: {}
      }),
    getQueryKey: () => ['playlists', 'quickaccess']
  });

  return {
    playlists: playlists?.result || []
  };
};
