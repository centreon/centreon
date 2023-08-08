import { useCallback, useEffect, useMemo } from 'react';

import { atom, useAtom } from 'jotai';

import {
  AccessRightsFormProps,
  ContactAccessRightStateResource
} from '@centreon/ui/components';

import {
  Dashboard,
  DashboardAccessRightsContact,
  DashboardAccessRightsContactGroup,
  DashboardsContact,
  DashboardsContactGroup
} from '../../api/models';
import routeMap from '../../../reactRoutes/routeMap';
import { useListDashboardsContacts } from '../../api/useListDashboardsContacts';
import { useListDashboardsContactGroups } from '../../api/useListDashboardsContactGroups';
import { List } from '../../api/meta.models';
import { useListAccessRightsContacts } from '../../api/useListAccessRightsContacts';
import { useListAccessRightsContactGroups } from '../../api/useListAccessRightsContactGroups';

import { transformAccessRightContactOrContactGroup } from './useDashboardAccessRights.utils';
import { useDashboardAccessRightsBatchUpdate } from './useDashboardAccessRightsBatchUpdate';

export const dialogStateAtom = atom<{
  dashboard: Dashboard | null;
  isOpen: boolean;
  status: 'idle' | 'loading';
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

const initialAccessRightsAtom = atom<AccessRightsFormProps['initialValues']>(
  []
);

type UseDashboardAccessRights = {
  closeDialog: () => void;
  dashboard: Dashboard | null;
  editAccessRights: (dashboard: Dashboard) => () => void;
  initialAccessRights: AccessRightsFormProps['initialValues'];
  isDialogOpen: boolean;
  options: AccessRightsFormProps['options'];
  resourceLink: string;
  status: 'idle' | 'loading';
  submit: AccessRightsFormProps['onSubmit'];
};

const useDashboardAccessRights = (): UseDashboardAccessRights => {
  const [dialogState, setDialogState] = useAtom(dialogStateAtom);

  const { batchUpdateAccessRights } = useDashboardAccessRightsBatchUpdate();

  /** options */

  const { data: dataContacts } = useListDashboardsContacts({
    options: { suspense: false },
    params: { limit: 1000 }
  });
  const { data: dataContactGroups } = useListDashboardsContactGroups({
    options: { suspense: false },
    params: { limit: 1000 }
  });

  const [options, setOptions] = useAtom(optionsAtom);

  // eslint-disable-next-line hooks/sort
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

  /** initial access rights */

  const {
    data: dataAccessRightsContacts,
    isFetching: isFetchingAccessRightsContacts
  } = useListAccessRightsContacts({
    dashboardId: (dialogState.dashboard?.id as number) ?? null,
    options: { suspense: false },
    params: { limit: 1000 }
  });
  const {
    data: dataAccessRightsContactGroups,
    isFetching: isFetchingAccessRightsContactGroups
  } = useListAccessRightsContactGroups({
    dashboardId: (dialogState.dashboard?.id as number) ?? null,
    options: { suspense: false },
    params: { limit: 1000 }
  });

  // eslint-disable-next-line hooks/sort
  const [initialAccessRights, setInitialAccessRights] = useAtom(
    initialAccessRightsAtom
  );

  useEffect(
    () =>
      setInitialAccessRights([
        ...((dataAccessRightsContacts &&
          (
            dataAccessRightsContacts as List<DashboardAccessRightsContact>
          ).result.map(transformAccessRightContactOrContactGroup)) ??
          []),
        ...((dataAccessRightsContactGroups &&
          (
            dataAccessRightsContactGroups as List<DashboardAccessRightsContactGroup>
          ).result.map(transformAccessRightContactOrContactGroup)) ??
          [])
      ]),
    [dataAccessRightsContacts, dataAccessRightsContactGroups]
  );

  useEffect(() => {
    setDialogState((prev) => ({
      ...prev,
      status:
        isFetchingAccessRightsContacts || isFetchingAccessRightsContactGroups
          ? 'loading'
          : 'idle'
    }));
  }, [isFetchingAccessRightsContacts, isFetchingAccessRightsContactGroups]);

  /** resource link */

  const resourceLink = useMemo(() => {
    const path = routeMap.dashboard.replace(
      ':dashboardId',
      (dialogState.dashboard?.id as string) ?? ''
    );

    return `${window.location.origin}${path}`;
  }, [dialogState.dashboard]);

  /** actions */

  const editAccessRights = (dashboard: Dashboard) => (): void =>
    setDialogState({
      ...dialogState,
      dashboard,
      isOpen: true
    });

  const closeDialog = (): void =>
    setDialogState({ ...dialogState, dashboard: null, isOpen: false });

  // eslint-disable-next-line hooks/sort
  const submit = useCallback(
    async (values: Array<ContactAccessRightStateResource>): Promise<void> => {
      batchUpdateAccessRights({
        entityId: dialogState.dashboard?.id as number,
        values
      });
      closeDialog();
    },
    [dialogState.dashboard?.id]
  );

  return {
    closeDialog,
    dashboard: dialogState.dashboard,
    editAccessRights,
    initialAccessRights,
    isDialogOpen: dialogState.isOpen,
    options,
    resourceLink,
    status: dialogState.status,
    submit
  };
};

export { useDashboardAccessRights };
