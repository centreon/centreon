import { useState } from 'react';

import { useAtom, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { generatePath } from 'react-router';

import routeMap from '../../../../reactRoutes/routeMap';
import {
  Dashboard,
  DashboardRole,
  FormattedDashboard,
  ShareType
} from '../../../api/models';
import { useDeleteAccessRightsContact } from '../../../api/useDeleteAccessRightsContact';
import { useDeleteAccessRightsContactGroup } from '../../../api/useDeleteAccessRightsContactGroup';
import { routerHooks } from '../../../routerHooks';

import {
  askBeforeRevokeAtom,
  limitAtom,
  pageAtom,
  sortFieldAtom,
  sortOrderAtom
} from './atom';
import { formatListingData } from './utils';

interface ConfirmRevokeAccessRightProps {
  dashboardId: string | number;
  id: string | number;
  type: ShareType;
}

interface UseListing {
  changePage: (updatedPage: number) => void;
  changeSort: ({ sortOrder, sortField }) => void;
  closeAskRevokeAccessRight: () => void;
  confirmRevokeAccessRight: (
    props: ConfirmRevokeAccessRightProps
  ) => () => void;
  formattedRows: Array<FormattedDashboard>;
  getRowProperty: (row) => string;
  navigateToDashboard: (row: FormattedDashboard) => void;
  page?: number;
  resetColumns: () => void;
  selectedColumnIds: Array<string>;
  setLimit;
  setSelectedColumnIds;
  sortf: string;
  sorto: 'asc' | 'desc';
}

const useListing = ({
  defaultColumnsIds,
  rows
}: {
  defaultColumnsIds: Array<string>;
  rows?: Array<Dashboard>;
}): UseListing => {
  const navigate = routerHooks.useNavigate();

  const [selectedColumnIds, setSelectedColumnIds] =
    useState<Array<string>>(defaultColumnsIds);

  const resetColumns = (): void => {
    setSelectedColumnIds(defaultColumnsIds);
  };

  const [sorto, setSorto] = useAtom(sortOrderAtom);
  const [sortf, setSortf] = useAtom(sortFieldAtom);
  const [page, setPage] = useAtom(pageAtom);
  const setLimit = useSetAtom(limitAtom);
  const setAskingBeforeRevoke = useSetAtom(askBeforeRevokeAtom);

  const { mutate: deleteAccessRightContact } = useDeleteAccessRightsContact();
  const { mutate: deleteAccessRightContactGroup } =
    useDeleteAccessRightsContactGroup();

  const changeSort = ({ sortOrder, sortField }): void => {
    setSortf(sortField);
    setSorto(sortOrder);
  };

  const changePage = (updatedPage): void => {
    setPage(updatedPage + 1);
  };

  const getRowProperty = (row): string => {
    if (equals(row?.ownRole, DashboardRole.viewer)) {
      return '';
    }

    return 'shares';
  };

  const closeAskRevokeAccessRight = (): void => {
    setAskingBeforeRevoke(null);
  };

  const confirmRevokeAccessRight =
    ({ dashboardId, id, type }: ConfirmRevokeAccessRightProps) =>
    (): void => {
      if (equals(type, ShareType.Contact)) {
        deleteAccessRightContact({ dashboardId, id });
        closeAskRevokeAccessRight();

        return;
      }

      deleteAccessRightContactGroup({ dashboardId, id });
      closeAskRevokeAccessRight();
    };

  const navigateToDashboard = (row: FormattedDashboard): void => {
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId: row.id
      })
    );
  };

  return {
    changePage,
    changeSort,
    closeAskRevokeAccessRight,
    confirmRevokeAccessRight,
    formattedRows: formatListingData(rows),
    getRowProperty,
    navigateToDashboard,
    page,
    resetColumns,
    selectedColumnIds,
    setLimit,
    setSelectedColumnIds,
    sortf,
    sorto
  };
};

export default useListing;
