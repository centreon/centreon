interface Contact {
  admin?: boolean;
  alias?: string | null;
  authenticationType?: 'local' | 'ldap';
  email: string;
  enableNotifications?: boolean;
  GUIAccess?: boolean;
  language?: string;
  name: string;
  password: string;
}

interface Token {
  name: string;
  userId: number;
  duration: keyof typeof durationMap;
}

const durationMap = {
  '7 days': 7,
  '30 days': 30,
  '60 days': 60,
  '90 days': 90,
  '1 year': 365
};

const columns = [
  'Status',
  'Name',
  'Creation Date',
  'Expiration Date',
  'User',
  'Creator'
];

const columnsFromLabels = [
  'Status',
  'Name',
  'Creation date',
  'Expiration date',
  'User',
  'Creator'
];

export { Contact, durationMap, Token, columns, columnsFromLabels };
