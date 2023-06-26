// TODO merge permission in refactored component

import { ReactElement, useMemo } from 'react';

import { dec, equals, gt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtom, useSetAtom } from 'jotai';
import { generatePath, useNavigate } from 'react-router-dom';

import AddIcon from '@mui/icons-material/Add';
import { CircularProgress } from '@mui/material';

import {
  TiledListingActions,
  TiledListingContent,
  TiledListingList
} from '@centreon/ui';
import { Button, DataTable } from '@centreon/ui/components';

import routeMap from '../reactRoutes/routeMap';

import useDashboards from './useDashboards';
import {
  labelCreateADashboard,
  labelNoDashboardsFound
} from './translatedLabels';
import { deleteDialogStateAtom, openDialogAtom } from './atoms';
import { Dashboard } from './models';
import useUserDashboardPermissions from './useUserDashboardPermissions';

const emptyListStateLabels = {
  actions: {
    create: labelCreateADashboard
  },
  title: labelNoDashboardsFound
};

const Listing = (): ReactElement => {
  const { t } = useTranslation();
  const { dashboards, elementRef, isLoading } = useDashboards();
  const { hasEditPermission, canCreateOrManagerDashboards } =
    useUserDashboardPermissions();

  const [, setDeleteDialogState] = useAtom(deleteDialogStateAtom);
  const openDialog = useSetAtom(openDialogAtom);

  const hasDashboards = useMemo(
    () => gt(dashboards.length, 0),
    [dashboards.length]
  );

  const createDashboard = (): void => {
    openDialog({
      dashboard: null,
      variant: 'create'
    });
  };

  const editDashboard = (dashboard: Dashboard) => (): void => {
    openDialog({
      dashboard,
      variant: 'update'
    });
  };

  const navigate = useNavigate();
  const navigateToDashboard = (dashboard: Dashboard) => (): void =>
    navigate(generatePath(routeMap.dashboard, { dashboardId: dashboard.id }));

  return (
    <TiledListingList>
      <TiledListingActions>
        {hasDashboards && canCreateOrManagerDashboards && (
          <Button
            aria-label="create"
            data-testid="create-dashboard"
            icon={<AddIcon />}
            iconVariant="start"
            onClick={createDashboard}
          >
            {t(labelCreateADashboard)}
          </Button>
        )}
      </TiledListingActions>
      <TiledListingContent>
        <DataTable isEmpty={!hasDashboards}>
          {!hasDashboards ? (
            <DataTable.EmptyState
              aria-label="create"
              canCreate={canCreateOrManagerDashboards}
              data-testid="create-dashboard"
              labels={emptyListStateLabels}
              onCreate={createDashboard}
            />
          ) : (
            dashboards.map((dashboard, index) => {
              const isLastElement = equals(index, dec(dashboards.length));

              return (
                <DataTable.Item
                  hasCardAction
                  description={dashboard.description ?? undefined}
                  hasActions={hasEditPermission(dashboard)}
                  key={dashboard.id}
                  ref={isLastElement ? elementRef : undefined}
                  title={dashboard.name}
                  onClick={navigateToDashboard(dashboard)}
                  onDelete={() =>
                    setDeleteDialogState({ item: dashboard, open: true })
                  }
                  onEdit={editDashboard(dashboard)}
                />
              );
            })
          )}
        </DataTable>
        {isLoading && (
          <div>
            <CircularProgress />
          </div>
        )}
      </TiledListingContent>
    </TiledListingList>
  );
};

export default Listing;
