export interface SharedAuthenticationConditionsToAPI {
  attribute_path?: string | null;
  authorized_values: Array<string>;
  is_enabled: boolean;
}

export interface GroupsRelationToAPI {
  contact_group_id: number;
  group_value: string;
}

export interface SharedGroupsMappingToAPI {
  attribute_path?: string | null;
  is_enabled: boolean;
  relations: Array<GroupsRelationToAPI>;
}

export interface RolesRelationToAPI {
  access_group_id: number;
  claim_value: string;
  priority: number;
}

export interface SharedRolesMappingToAPI {
  apply_only_first_role: boolean;
  attribute_path?: string | null;
  is_enabled: boolean;
  relations: Array<RolesRelationToAPI>;
}
