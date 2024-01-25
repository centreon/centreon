import { ReactElement } from 'react';

import { generatePath, useNavigate } from 'react-router-dom';
import { equals } from 'ramda';

import { PageLayout } from '@centreon/ui/components';

import { Dashboard } from '../../../api/models';
import routeMap from '../../../../reactRoutes/routeMap';
import {
  labelCreateADashboard,
  labelDashboardLibrary
} from '../../../translatedLabels';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';
import { DashboardLayout } from '../../../models';

import { useDashboardsQuickAccess } from './useDashboardsQuickAccess';

type DashboardsQuickAccessMenuProps = {
  dashboard?: Dashboard;
};

const DashboardsQuickAccessMenu = ({
  dashboard
}: DashboardsQuickAccessMenuProps): ReactElement => {
  const { dashboards } = useDashboardsQuickAccess();

  const { createDashboard } = useDashboardConfig();

  const navigate = useNavigate();
  const navigateToDashboard = (dashboardId: string | number) => (): void =>
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId,
        layout: DashboardLayout.Library
      })
    );

  const navigateToDashboardLibrary = (): void =>
    navigate(
      generatePath(routeMap.dashboards, { layout: DashboardLayout.Library })
    );

  return (
    <PageLayout.QuickAccess
      create={createDashboard}
      elements={dashboards}
      goBack={navigateToDashboardLibrary}
      isActive={(id) => equals(id, Number(dashboard?.id))}
      labels={{
        create: labelCreateADashboard,
        goBack: labelDashboardLibrary
      }}
      navigateToElement={navigateToDashboard}
    />
  );
};

export { DashboardsQuickAccessMenu };
