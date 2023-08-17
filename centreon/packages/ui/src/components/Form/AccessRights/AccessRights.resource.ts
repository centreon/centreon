export type ContactAccessRightState =
  | 'added'
  | 'updated'
  | 'removed'
  | 'unchanged';

export type ContactAccessRightStateResource = {
  contactAccessRight: ContactAccessRightResource;
  state: ContactAccessRightState;
  stateHistory: Array<ContactAccessRightState>;
};

export type ContactAccessRightResource = {
  contact: ContactResource | ContactGroupResource | null;
  role: RoleResource['role'];
};

export type ContactResource = {
  email?: string;
  id?: number | string;
  name: string;
  type: 'contact';
};

export const isContactResource = (
  resource: ContactResource | ContactGroupResource | null
): resource is ContactResource => {
  return resource?.type === 'contact';
};

export type ContactGroupResource = {
  id?: number | string;
  name: string;
  type: 'contact_group';
};

export const isContactGroupResource = (
  resource: ContactResource | ContactGroupResource | null
): resource is ContactResource => {
  return resource?.type === 'contact_group';
};

export type RoleResource = {
  role: 'viewer' | 'editor' | string;
};
