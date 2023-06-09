import { ReactElement, useMemo } from 'react';

import { gt } from 'ramda';
import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';
import { generatePath, useNavigate } from 'react-router-dom';

import AddIcon from '@mui/icons-material/Add';
import { CircularProgress } from '@mui/material';

import { Button, DataTable, PageLayout } from '@centreon/ui/components';

import routeMap from '../reactRoutes/routeMap';

import useDashboards from './useDashboards';
import {
  labelCreateADashboard,
  labelNoDashboardsFound
} from './translatedLabels';
import { deleteDialogStateAtom } from './atoms';
import { Dashboard } from './models';
import { useDashboardForm } from './components/DashboardFormModal/useDashboardForm';
import { useDashboardAccessRights } from './components/DashboardAccessRightsModal/useDashboardAccessRights';

const emptyListStateLabels = {
  actions: {
    create: labelCreateADashboard
  },
  title: labelNoDashboardsFound
};

const Listing = (): ReactElement => {
  const { t } = useTranslation();
  const { dashboards, elementRef, isLoading } = useDashboards();

  const [, setDeleteDialogState] = useAtom(deleteDialogStateAtom);

  const { createDashboard, editDashboard } = useDashboardForm();
  const { editAccessRights } = useDashboardAccessRights();

  const hasDashboards = useMemo(
    () => gt(dashboards.length, 0),
    [dashboards.length]
  );

  const navigate = useNavigate();
  const navigateToDashboard = (dashboard: Dashboard) => (): void =>
    navigate(generatePath(routeMap.dashboard, { dashboardId: dashboard.id }));

  return (
    <>
      <PageLayout.Actions>
        {hasDashboards && (
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
      </PageLayout.Actions>

      <DataTable isEmpty={!hasDashboards}>
        {!hasDashboards ? (
          <DataTable.EmptyState
            aria-label="create"
            data-testid="create-dashboard"
            labels={emptyListStateLabels}
            onCreate={createDashboard}
          />
        ) : (
          dashboards.map((dashboard, index) => {
            return (
              <DataTable.Item
                hasActions
                hasCardAction
                description={dashboard.description ?? undefined}
                key={dashboard.id}
                title={dashboard.name}
                onClick={navigateToDashboard(dashboard)}
                onDelete={() =>
                  setDeleteDialogState({ item: dashboard, open: true })
                }
                onEdit={editDashboard(dashboard)}
                onEditAccessRights={editAccessRights(dashboard)}
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
    </>
  );
};

export default Listing;
