export interface AccessRightInitialValues {
  email?: string;
  id: number | string;
  isContactGroup: boolean;
  most_permissive_role?: 'editor' | 'viewer';
  name: string;
  role: string;
}

export interface AccessRight extends AccessRightInitialValues {
  isAdded: boolean;
  isRemoved: boolean;
  isUpdated: boolean;
}

export interface Labels {
  actions: {
    cancel: string;
    save: string;
  };
  add: {
    autocompleteContact: string;
    autocompleteContactGroup: string;
    contact: string;
    contactGroup: string;
    title: string;
  };
  list: {
    added: string;
    empty: string;
    group: string;
    removed: string;
    title: string;
    updated: string;
  };
}

export interface Endpoints {
  contact: string;
  contactGroup: string;
}

export enum ItemState {
  added = 'added',
  removed = 'removed',
  updated = 'updated'
}

export enum ContactType {
  Contact = 'contact',
  ContactGroup = 'contactGroup'
}
