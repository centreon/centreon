import { concat, isEmpty, isNil, map } from 'ramda';

import { PlaylistListingType, Share, ShareType } from './models';

const formatShares = ({ shares }: { shares: Share }): unknown => {
  const contacts =
    shares?.contacts?.map((contact) => ({
      id: contact.id,
      role: contact.role,
      shareName: contact.name,
      type: ShareType.Contact
    })) || [];
  const contactgroups =
    shares?.contactgroups?.map((contact) => ({
      id: contact.id,
      role: contact.role,
      shareName: contact.name,
      type: ShareType.ContactGroup
    })) || [];

  const formatedShare = concat(contacts, contactgroups);

  return formatedShare;
};

export const formatListingData = ({
  data
}: {
  data?: PlaylistListingType;
}): PlaylistListingType | undefined => {
  if (isEmpty(data?.result) || isNil(data?.result)) {
    return data;
  }

  const result = map(
    (item) => {
      return {
        ...item,
        shares: formatShares({ shares: item.shares })
      };
    },
    data?.result || []
  );

  const listingData = { ...data, result };

  return listingData;
};
