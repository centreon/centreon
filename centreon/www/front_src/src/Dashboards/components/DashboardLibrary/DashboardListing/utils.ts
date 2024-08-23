import { concat, equals, map, omit } from 'ramda';

import {
  Dashboard,
  FormattedDashboard,
  FormattedShare,
  ShareType,
  Shares
} from '../../../api/models';

const formatShares = ({
  shares,
  dashboardId
}: {
  dashboardId: string | number;
  shares: Shares;
}): Array<FormattedShare> => {
  const contacts =
    shares?.contacts?.map((contact) => ({
      dashboardId,
      id: contact.id,
      name: contact.name,
      role: contact.role,
      type: ShareType.Contact
    })) || [];
  const contactgroups =
    shares?.contactGroups?.map((contact) => ({
      dashboardId,
      id: contact.id,
      name: contact.name,
      role: contact.role,
      type: ShareType.ContactGroup
    })) || [];

  const formatedShare = concat(contacts, contactgroups);

  return formatedShare;
};

export const formatListingData = (
  rows?: Array<Dashboard>
): Array<FormattedDashboard> => {
  const result = map((item) => {
    return {
      ...item,
      shares: formatShares({ dashboardId: item.id, shares: item.shares })
    };
  }, rows || []);

  return result;
};

export const unformatDashboard = (dashboard: FormattedDashboard): Dashboard => {
  const contacts = dashboard.shares
    .filter(({ type }) => equals(type, ShareType.Contact))
    .map(omit(['dashboardId']));
  const contactGroups = dashboard.shares
    .filter(({ type }) => equals(type, ShareType.ContactGroup))
    .map(omit(['dashboardId']));

  return {
    ...dashboard,
    shares: {
      contactGroups,
      contacts
    }
  };
};
