export interface UseTokenListing {
  data: Array<unknown>;
}

export interface PersonalInformation {
  id: number;
  name: string;
}

export enum Columns {
  actions = 'Actions',
  creationDate = 'Creation Date',
  creator = 'Creator',
  expirationDate = 'Expiration Date',
  name = 'Name',
  status = 'Status',
  user = 'User'
}
export interface Token {
  creation_date: string;
  creator: PersonalInformation;
  expiration_date: string;
  is_revoked: boolean;
  name: string;
  user: PersonalInformation;
}
