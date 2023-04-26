import { JsonDecoder } from 'ts.data.json';

import { GroupsRelation, NamedEntity, RolesRelation } from './models';

export const getNamedEntityDecoder = (
  title: string
): JsonDecoder.Decoder<NamedEntity> =>
  JsonDecoder.object<NamedEntity>(
    {
      id: JsonDecoder.number,
      name: JsonDecoder.string
    },
    title
  );

export const contactTemplateDecoder = JsonDecoder.nullable(
  getNamedEntityDecoder('Contact template')
);

const groupsRelationDecoder = JsonDecoder.object<GroupsRelation>(
  {
    contactGroup: getNamedEntityDecoder('Contact group'),
    groupValue: JsonDecoder.string
  },
  'Group relation',
  {
    contactGroup: 'contact_group',
    groupValue: 'group_value'
  }
);

export const groupsRelationsDecoder = JsonDecoder.array(
  groupsRelationDecoder,
  'Groups relations'
);

const rolesRelationDecoder = JsonDecoder.object<RolesRelation>(
  {
    accessGroup: getNamedEntityDecoder('Access group'),
    claimValue: JsonDecoder.string,
    priority: JsonDecoder.number
  },
  'Role relation',
  {
    accessGroup: 'access_group',
    claimValue: 'claim_value'
  }
);

export const rolesRelationsDecoder = JsonDecoder.array(
  rolesRelationDecoder,
  'Roles relations'
);
