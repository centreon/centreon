import { map } from 'ramda';

import { GroupsRelation, RolesRelation } from './models';
import { GroupsRelationToAPI, RolesRelationToAPI } from './modelsAPI';

export const adaptRolesRelationsToAPI = (
  relations: Array<RolesRelation>
): Array<RolesRelationToAPI> =>
  map(
    ({ claimValue, accessGroup, priority }) => ({
      access_group_id: accessGroup.id,
      claim_value: claimValue,
      priority
    }),
    relations
  );

export const adaptGroupsRelationsToAPI = (
  relations: Array<GroupsRelation>
): Array<GroupsRelationToAPI> =>
  map(
    ({ groupValue, contactGroup }) => ({
      contact_group_id: contactGroup.id,
      group_value: groupValue
    }),
    relations
  );
