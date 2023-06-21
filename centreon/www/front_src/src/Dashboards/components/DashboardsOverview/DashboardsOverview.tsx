import { ReactElement, useMemo } from 'react';

import { useTranslation } from 'react-i18next';
import { generatePath, useNavigate } from 'react-router-dom';

import AddIcon from '@mui/icons-material/Add';

import { Button, DataTable, PageLayout } from '@centreon/ui/components';

import { useDashboardAccessRights } from '../DashboardAccessRights/useDashboardAccessRights';
import { useDashboardDelete } from '../DashboardDelete/useDashboardDelete';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';
import {
  labelCreateADashboard,
  labelNoDashboardsFound
} from '../../translatedLabels';
import { Dashboard } from '../../api/models';
import routeMap from '../../../reactRoutes/routeMap';

import { useDashboardsOverview } from './useDashboardsOverview';

const DashboardsOverview = (): ReactElement => {
  const { t } = useTranslation();

  const { isEmptyList, dashboards } = useDashboardsOverview();
  const { createDashboard, editDashboard } = useDashboardConfig();
  const { deleteDashboard } = useDashboardDelete();
  const { editAccessRights } = useDashboardAccessRights();

  const navigate = useNavigate();
  const navigateToDashboard = (dashboard: Dashboard) => (): void =>
    navigate(generatePath(routeMap.dashboard, { dashboardId: dashboard.id }));

  const labels = useMemo(
    () => ({
      actions: {
        create: t(labelCreateADashboard)
      },
      emptyState: {
        actions: {
          create: t(labelCreateADashboard)
        },
        title: t(labelNoDashboardsFound)
      }
    }),
    []
  );

  return (
    <>
      <PageLayout.Actions>
        {!isEmptyList && (
          <Button
            aria-label="create"
            data-testid="create-dashboard"
            icon={<AddIcon />}
            iconVariant="start"
            onClick={createDashboard}
          >
            {labels.actions.create}
          </Button>
        )}
      </PageLayout.Actions>

      <DataTable isEmpty={isEmptyList}>
        {isEmptyList ? (
          <DataTable.EmptyState
            aria-label="create"
            data-testid="create-dashboard"
            labels={labels.emptyState}
            onCreate={createDashboard}
          />
        ) : (
          dashboards.map((dashboard) => (
            <DataTable.Item
              hasActions
              hasCardAction
              description={dashboard.description ?? undefined}
              key={dashboard.id}
              title={dashboard.name}
              onClick={navigateToDashboard(dashboard)}
              onDelete={deleteDashboard(dashboard)}
              onEdit={editDashboard(dashboard)}
              onEditAccessRights={editAccessRights(dashboard)}
            />
          ))
        )}
      </DataTable>
    </>
  );
};

export { DashboardsOverview };
