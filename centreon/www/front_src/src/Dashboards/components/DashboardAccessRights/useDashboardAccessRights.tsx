import { useEffect, useMemo } from 'react';

import { atom, useAtom } from 'jotai';

import { AccessRightsFormProps } from '@centreon/ui/components';

import {
  Dashboard,
  DashboardsContact,
  DashboardsContactGroup
} from '../../api/models';
import routeMap from '../../../reactRoutes/routeMap';
import { useListDashboardsContacts } from '../../api/useListDashboardsContacts';
import { useListDashboardsContactGroups } from '../../api/useListDashboardsContactGroups';
import { List } from '../../api/meta.models';

const dialogStateAtom = atom<{
  dashboard: Dashboard | null;
  isOpen: boolean;
  status: 'idle' | 'loading' | 'success' | 'error';
}>({
  dashboard: null,
  isOpen: false,
  status: 'idle'
});

const optionsAtom = atom<{
  contacts: Array<DashboardsContact | DashboardsContactGroup>;
  roles: Array<{ role: 'viewer' | 'editor' }>;
}>({
  contacts: [],
  roles: [{ role: 'viewer' }, { role: 'editor' }]
});

type UseDashboardAccessRights = {
  closeDialog: () => void;
  dashboard: Dashboard | null;
  editAccessRights: (dashboard: Dashboard) => () => void;
  isDialogOpen: boolean;
  options: AccessRightsFormProps['options'];
  resourceLink: string;
  status: 'idle' | 'loading' | 'success' | 'error';
  submit: (dashboard: Dashboard) => void;
};

const useDashboardAccessRights = (): UseDashboardAccessRights => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const { data: dataContacts } = useListDashboardsContacts({
    params: { limit: 1000 }
  });
  const { data: dataContactGroups } = useListDashboardsContactGroups({
    params: { limit: 1000 }
  });

  const [options, setOptions] = useAtom(optionsAtom);

  useEffect(() => {
    setOptions((prev) => ({
      ...prev,
      contacts:
        dataContacts && dataContactGroups
          ? [
              ...((dataContacts as List<DashboardsContact>).result ?? []),
              ...((dataContactGroups as List<DashboardsContactGroup>).result ??
                [])
            ].sort((a, b): number => a.name.localeCompare(b.name) || 0)
          : []
    }));
  }, [dataContacts, dataContactGroups]);

  const closeDialog = (): void =>
    setDialogState({ ...dialogState, isOpen: false });

  const editAccessRights = (dashboard: Dashboard) => (): void => {
    setDialogState({
      ...dialogState,
      dashboard,
      isOpen: true
    });
  };

  const resourceLink = useMemo(() => {
    const path = routeMap.dashboard.replace(
      ':dashboardId',
      (dialogState.dashboard?.id as string) ?? ''
    );

    return `${window.location.origin}${path}`;
  }, [dialogState.dashboard]);

  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  const submit = async (dashboard: Dashboard): Promise<void> => {
    setDialogState({ ...dialogState, isOpen: false });
  };

  return {
    closeDialog,
    dashboard: dialogState.dashboard,
    editAccessRights,
    isDialogOpen: dialogState.isOpen,
    options,
    resourceLink,
    status: dialogState.status,
    submit
  };
};

export { useDashboardAccessRights };
