import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { generatePath, useNavigate } from 'react-router-dom';
import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';

import { DataTable } from '@centreon/ui/components';

import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';
import {
  labelCreateADashboard,
  labelWelcomeToDashboardInterface
} from '../../../translatedLabels';
import { Dashboard } from '../../../api/models';
import routeMap from '../../../../reactRoutes/routeMap';
import { useDashboardUserPermissions } from '../DashboardUserPermissions/useDashboardUserPermissions';
import { DashboardLayout } from '../../../models';
import { DashboardListing } from '../DashboardListing';
import { viewModeAtom, searchAtom } from '../DashboardListing/atom';
import { ViewMode } from '../DashboardListing/models';
import DashboardCardActions from '../DashboardCardActions/DashboardCardActions';

import { useDashboardsOverview } from './useDashboardsOverview';
import { useStyles } from './DashboardsOverview.styles';
import { DashboardsOverviewSkeleton } from './DashboardsOverviewSkeleton';

const DashboardsOverview = (): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const viewMode = useAtomValue(viewModeAtom);
  const search = useAtomValue(searchAtom);

  const { isEmptyList, dashboards, data, isLoading } = useDashboardsOverview();
  const { createDashboard } = useDashboardConfig();
  const { hasEditPermission, canCreateOrManageDashboards } =
    useDashboardUserPermissions();

  const navigate = useNavigate();

  const navigateToDashboard = (dashboard: Dashboard) => (): void =>
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId: dashboard.id,
        layout: DashboardLayout.Library
      })
    );

  const isCardsView = useMemo(
    () => equals(viewMode, ViewMode.Cards),
    [viewMode]
  );

  const emptyStateLabels = {
    actions: {
      create: t(labelCreateADashboard)
    },
    title: t(labelWelcomeToDashboardInterface)
  };

  if (isCardsView && isLoading && isNil(data)) {
    return <DashboardsOverviewSkeleton />;
  }

  if (isEmptyList && !search && !isLoading) {
    return (
      <DataTable isEmpty={isEmptyList} variant="grid">
        <DataTable.EmptyState
          aria-label="create"
          canCreate={canCreateOrManageDashboards}
          data-testid="create-dashboard"
          labels={emptyStateLabels}
          onCreate={createDashboard}
        />
      </DataTable>
    );
  }

  const GridTable = (
    <DataTable isEmpty={isEmptyList} variant="grid">
      {dashboards.map((dashboard) => (
        <DataTable.Item
          hasCardAction
          Actions={<DashboardCardActions dashboard={dashboard} />}
          description={dashboard.description ?? undefined}
          hasActions={hasEditPermission(dashboard)}
          key={dashboard.id}
          title={dashboard.name}
          onClick={navigateToDashboard(dashboard)}
        />
      ))}
    </DataTable>
  );

  return (
    <div className={classes.container}>
      <DashboardListing
        customListingComponent={GridTable}
        data={data}
        displayCustomListing={isCardsView}
        loading={isLoading}
        openConfig={createDashboard}
      />
    </div>
  );
};

export { DashboardsOverview };
