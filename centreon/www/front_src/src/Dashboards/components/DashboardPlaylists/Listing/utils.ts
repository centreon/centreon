import { concat } from 'ramda';

import { Share, ShareType } from './models';

export const formatShares = ({ shares }: { shares: Share }): unknown => {
  const contacts = shares?.contacts.map((contact) => ({
    id: contact.id,
    role: contact.role,
    shareName: contact.name,
    type: ShareType.Contact
  }));
  const contactgroups = shares?.contactgroups.map((contact) => ({
    id: contact.id,
    role: contact.role,
    shareName: contact.name,
    type: ShareType.ContactGroup
  }));
  const formatedShare = concat(contacts, contactgroups);

  return formatedShare;
};
