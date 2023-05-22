export interface SharedAuthenticationConditions {
  attributePath?: string | null;
  authorizedValues: Array<string>;
  isEnabled: boolean;
}

export interface NamedEntity {
  id: number;
  name: string;
}

export interface GroupsRelation {
  contactGroup: NamedEntity;
  groupValue: string;
}

export interface SharedGroupsMapping {
  attributePath?: string | null;
  isEnabled: boolean;
  relations: Array<GroupsRelation>;
}

export interface RolesRelation {
  accessGroup: NamedEntity;
  claimValue: string;
  priority: number;
}

export interface SharedRolesMapping {
  applyOnlyFirstRole: boolean;
  attributePath?: string | null;
  isEnabled: boolean;
  relations: Array<RolesRelation>;
}
