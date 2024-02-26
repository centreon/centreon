import { ReactElement, useMemo, useCallback } from 'react';

import { useTranslation } from 'react-i18next';
import { generatePath, useNavigate } from 'react-router-dom';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import { DataTable } from '@centreon/ui/components';

import { useDashboardDelete } from '../../../hooks/useDashboardDelete';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';
import {
  labelCancel,
  labelCreateADashboard,
  labelDelete,
  labelDescriptionDeleteDashboard,
  labelWelcomeToDashboardInterface
} from '../../../translatedLabels';
import { Dashboard } from '../../../api/models';
import routeMap from '../../../../reactRoutes/routeMap';
import { useDashboardUserPermissions } from '../DashboardUserPermissions/useDashboardUserPermissions';
import { DashboardLayout } from '../../../models';
import { DashboardListing } from '../DashboardListing';
import { viewModeAtom, searchAtom } from '../DashboardListing/atom';
import { ViewMode } from '../DashboardListing/models';
import { isSharesOpenAtom } from '../../../atoms';

import { useDashboardsOverview } from './useDashboardsOverview';
import { useStyles } from './DashboardsOverview.styles';

const DashboardsOverview = (): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const viewMode = useAtomValue(viewModeAtom);
  const search = useAtomValue(searchAtom);

  const { isEmptyList, dashboards, data, isLoading } = useDashboardsOverview();
  const { createDashboard, editDashboard } = useDashboardConfig();
  const deleteDashboard = useDashboardDelete();
  const { hasEditPermission, canCreateOrManageDashboards } =
    useDashboardUserPermissions();

  const navigate = useNavigate();

  const setIsSharesOpenAtom = useSetAtom(isSharesOpenAtom);

  const navigateToDashboard = (dashboard: Dashboard) => (): void =>
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId: dashboard.id,
        layout: DashboardLayout.Library
      })
    );

  const labels = useMemo(
    () => ({
      actions: {
        create: t(labelCreateADashboard)
      },
      emptyState: {
        actions: {
          create: t(labelCreateADashboard)
        },
        title: t(labelWelcomeToDashboardInterface)
      }
    }),
    []
  );

  const getLabelsDelete = useCallback((dashboard: Dashboard) => {
    return {
      cancel: t(labelCancel),
      confirm: {
        label: t(labelDelete),
        secondaryLabel: t(labelDescriptionDeleteDashboard, {
          name: dashboard.name
        })
      }
    };
  }, []);

  const editAccessRights = useCallback(
    (dashboard) => () => setIsSharesOpenAtom(dashboard),
    []
  );

  if (isEmptyList && !search) {
    return (
      <DataTable isEmpty={isEmptyList} variant="grid">
        <DataTable.EmptyState
          aria-label="create"
          canCreate={canCreateOrManageDashboards}
          data-testid="create-dashboard"
          labels={labels.emptyState}
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
          description={dashboard.description ?? undefined}
          hasActions={hasEditPermission(dashboard)}
          key={dashboard.id}
          labelsDelete={getLabelsDelete(dashboard)}
          title={dashboard.name}
          onClick={navigateToDashboard(dashboard)}
          onDelete={deleteDashboard(dashboard)}
          onEdit={editDashboard(dashboard)}
          onEditAccessRights={editAccessRights(dashboard)}
        />
      ))}
    </DataTable>
  );

  return (
    <div className={classes.container}>
      <DashboardListing
        customListingComponent={GridTable}
        data={data}
        displayCustomListing={equals(viewMode, ViewMode.Cards)}
        loading={isLoading}
        openConfig={createDashboard}
      />
    </div>
  );
};

export { DashboardsOverview };
