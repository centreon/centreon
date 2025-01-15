import { useAtom } from 'jotai';
import { concat } from 'ramda';
import { generatePath } from 'react-router';

import { centreonBaseURL } from '@centreon/ui';

import { UserRole } from '../../../api/models';
import { isSharesOpenAtom } from '../../../atoms';

interface UseDashboardAccessRightsState {
  close: () => void;
  dashboardId?: string | number;
  link: string;
  modalOpen: boolean;
  shares: Array<UserRole & { isContactGroup: boolean }>;
}

export const useDashboardAccessRights = (): UseDashboardAccessRightsState => {
  const [isSharesOpen, setIsSharesOpen] = useAtom(isSharesOpenAtom);

  const close = (): void => {
    setIsSharesOpen(null);
  };

  const contacts = (isSharesOpen?.shares.contacts || []).map((contact) => ({
    ...contact,
    isContactGroup: false
  }));
  const contactGroups = (isSharesOpen?.shares.contactGroups || []).map(
    (contactGroup) => ({
      ...contactGroup,
      isContactGroup: true
    })
  );

  const link = `${window.location.origin}${centreonBaseURL}${generatePath(
    '/home/dashboards/library/:id',
    {
      id: `${isSharesOpen?.id}` || ''
    }
  )}`;

  const shares = concat(contacts, contactGroups);

  const dashboardId = isSharesOpen?.id;

  return {
    close,
    dashboardId,
    link,
    modalOpen: Boolean(isSharesOpen),
    shares
  };
};
