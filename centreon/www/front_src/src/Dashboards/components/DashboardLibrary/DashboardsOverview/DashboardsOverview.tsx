import { ReactElement, useMemo, useCallback } from 'react';

import { useTranslation } from 'react-i18next';
import { generatePath, useNavigate } from 'react-router-dom';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';

import AddIcon from '@mui/icons-material/Add';

import { Button, DataTable, PageLayout } from '@centreon/ui/components';

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
import { isSharesOpenAtom } from '../../../atoms';
import { DashboardListing } from '../DashboardListing';
import { viewModeAtom, searchAtom } from '../DashboardListing/atom';
import { ViewMode } from '../DashboardListing/models';

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

  return (
    <>
      <PageLayout.Actions>
        {!isEmptyList && canCreateOrManageDashboards && (
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
      <div className={classes.container}>
        <DashboardListing
          customListingComponent={GridTable}
          data={data}
          displayCustomListing={equals(viewMode, ViewMode.Cards)}
          loading={isLoading}
          openConfig={createDashboard}
        />
      </div>
    </>
  );
};

export { DashboardsOverview };
