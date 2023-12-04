import { JsonDecoder } from 'ts.data.json';

import { OwnRole, Playlist } from '../models';

export const namedEntityDecoder = JsonDecoder.object(
  {
    id: JsonDecoder.number,
    name: JsonDecoder.string
  },
  'entity'
);

export const playlistDecoder = JsonDecoder.object<Playlist>(
  {
    author: namedEntityDecoder,
    createdAt: JsonDecoder.string,
    dashboards: JsonDecoder.array(
      JsonDecoder.object(
        {
          id: JsonDecoder.number,
          name: JsonDecoder.string,
          order: JsonDecoder.number
        },
        'dashboard'
      ),
      'dashboards'
    ),
    description: JsonDecoder.string,
    id: JsonDecoder.number,
    isPublic: JsonDecoder.boolean,
    name: JsonDecoder.string,
    ownRole: JsonDecoder.enumeration<OwnRole>(OwnRole, 'role'),
    publicLink: JsonDecoder.nullable(JsonDecoder.string),
    rotationTime: JsonDecoder.number,
    shares: JsonDecoder.object(
      {
        contactgroups: JsonDecoder.array(
          JsonDecoder.object(
            {
              id: JsonDecoder.number,
              name: JsonDecoder.string,
              role: JsonDecoder.string
            },
            'contactgroup'
          ),
          'contactgroups'
        ),
        contacts: JsonDecoder.array(
          JsonDecoder.object(
            {
              id: JsonDecoder.number,
              name: JsonDecoder.string,
              role: JsonDecoder.string
            },
            'contact'
          ),
          'contacts'
        )
      },
      'shares'
    ),
    updatedAt: JsonDecoder.string
  },
  'playlist',
  {
    createdAt: 'created_at',
    isPublic: 'is_public',
    ownRole: 'own_role',
    publicLink: 'public_link',
    rotationTime: 'rotation_time',
    updatedAt: 'updated_at'
  }
);
