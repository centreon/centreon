interface Contact {
  GUIAccess?: boolean;
  admin?: boolean;
  alias?: string | null;
  authenticationType?: 'local' | 'ldap';
  email: string;
  enableNotifications?: boolean;
  language?: string;
  name: string;
  password: string;
}

interface Token {
  duration: keyof typeof durationMap;
  name: string;
  userId: number;
}

const durationMap = {
  '1 year': 365,
  '7 days': 7,
  '30 days': 30,
  '60 days': 60,
  '90 days': 90
};

const columns = ['Name', 'Creation Date', 'Expiration Date', 'User', 'Creator'];

const columnsFromLabels = [
  'Name',
  'Creation date',
  'Expiration date',
  'User',
  'Creator'
];

export { Contact, durationMap, Token, columns, columnsFromLabels };
