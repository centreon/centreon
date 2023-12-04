import { JsonDecoder } from 'ts.data.json';

import { buildListingDecoder } from '@centreon/ui';

import {
  Contact,
  Dashboard,
  NamedEntity,
  PlaylistType,
  Role,
  Share
} from '../models';

const authorDecoder = JsonDecoder.object<NamedEntity>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'Author'
);

const dashboardDecoder = JsonDecoder.object<Dashboard>(
  {
    id: JsonDecoder.number,
    order: JsonDecoder.number
  },
  'Dashboard'
);

const contactDecoder = JsonDecoder.object<Contact>(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string,
    role: JsonDecoder.enumeration(Role, 'role')
  },
  'Contact'
);

const shareDecoder = JsonDecoder.object<Share>(
  {
    contactgroups: JsonDecoder.optional(
      JsonDecoder.array(contactDecoder, 'contact groups')
    ),
    contacts: JsonDecoder.optional(
      JsonDecoder.array(contactDecoder, 'contacts')
    )
  },
  'Share'
);

const PlaylistDecoder = JsonDecoder.object<PlaylistType>(
  {
    author: authorDecoder,
    createdAt: JsonDecoder.string,
    dashboards: JsonDecoder.array(dashboardDecoder, 'dashboards'),
    description: JsonDecoder.string,
    id: JsonDecoder.number,
    isPublic: JsonDecoder.nullable(JsonDecoder.boolean),
    name: JsonDecoder.string,
    ownRole: JsonDecoder.enumeration(Role, 'ownRole'),
    publicLink: JsonDecoder.nullable(JsonDecoder.string),
    rotationTime: JsonDecoder.number,
    shares: shareDecoder,
    updatedAt: JsonDecoder.string
  },
  'Playlist',
  {
    createdAt: 'created_at',
    isPublic: 'is_public',
    ownRole: 'own_role',
    publicLink: 'public_link',
    rotationTime: 'rotation_time',
    updatedAt: 'updated_at'
  }
);

const listPlaylistsDecoder = buildListingDecoder<PlaylistType>({
  entityDecoder: PlaylistDecoder,
  entityDecoderName: 'Playlists',
  listingDecoderName: 'PlaylistListing'
});

export default listPlaylistsDecoder;
