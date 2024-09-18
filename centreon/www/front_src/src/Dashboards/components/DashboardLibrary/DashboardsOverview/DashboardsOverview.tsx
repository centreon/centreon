import { ReactElement, useMemo } from 'react';

import { useAtomValue } from 'jotai';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { generatePath, useNavigate } from 'react-router-dom';

import InfoOutlinedIcon from '@mui/icons-material/InfoOutlined';
import { Box } from '@mui/material';

import { userAtom } from '@centreon/ui-context';
import { DataTable, Tooltip } from '@centreon/ui/components';

import thumbnailFallbackDark from '../../../../assets/thumbnail-fallback-dark.svg';
import thumbnailFallbackLight from '../../../../assets/thumbnail-fallback-light.svg';
import routeMap from '../../../../reactRoutes/routeMap';
import { Dashboard } from '../../../api/models';
import { DashboardLayout } from '../../../models';
import {
  labelCreateADashboard,
  labelSaveYourDashboardForThumbnail,
  labelWelcomeToDashboardInterface
} from '../../../translatedLabels';
import DashboardCardActions from '../DashboardCardActions/DashboardCardActions';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';
import { DashboardListing } from '../DashboardListing';
import { searchAtom, viewModeAtom } from '../DashboardListing/atom';
import { ViewMode } from '../DashboardListing/models';
import { useDashboardUserPermissions } from '../DashboardUserPermissions/useDashboardUserPermissions';

import { useStyles } from './DashboardsOverview.styles';
import { DashboardsOverviewSkeleton } from './DashboardsOverviewSkeleton';
import { useDashboardsOverview } from './useDashboardsOverview';

const DashboardsOverview = (): ReactElement => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const viewMode = useAtomValue(viewModeAtom);
  const search = useAtomValue(searchAtom);
  const user = useAtomValue(userAtom);

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

  const fallbackThumbnail = useMemo(
    () =>
      equals(user.themeMode, 'light')
        ? thumbnailFallbackLight
        : thumbnailFallbackDark,
    [user.themeMode]
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
        <div className={classes.dashboardItemContainer} key={dashboard.id}>
          <DataTable.Item
            hasCardAction
            Actions={<DashboardCardActions dashboard={dashboard} />}
            description={dashboard.description ?? undefined}
            hasActions={hasEditPermission(dashboard)}
            thumbnail={
              dashboard.thumbnail
                ? `${dashboard.thumbnail}?${new Date().getTime()}`
                : fallbackThumbnail
            }
            title={dashboard.name}
            onClick={navigateToDashboard(dashboard)}
          />
          {!dashboard.thumbnail && (
            <Box className={classes.thumbnailFallbackIcon}>
              <Tooltip
                followCursor={false}
                label={t(labelSaveYourDashboardForThumbnail)}
                placement="top"
              >
                <InfoOutlinedIcon
                  color="primary"
                  data-testid="thumbnail-fallback"
                />
              </Tooltip>
            </Box>
          )}
        </div>
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
