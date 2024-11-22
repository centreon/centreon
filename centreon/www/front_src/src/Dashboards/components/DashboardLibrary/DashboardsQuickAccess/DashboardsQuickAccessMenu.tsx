import { ReactElement } from 'react';

import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { generatePath, useNavigate } from 'react-router-dom';

import { PageLayout } from '@centreon/ui/components';

import routeMap from '../../../../reactRoutes/routeMap';
import { isEditingAtom } from '../../../SingleInstancePage/Dashboard/atoms';
import { Dashboard } from '../../../api/models';
import { DashboardLayout } from '../../../models';
import {
  labelCreateADashboard,
  labelDashboards
} from '../../../translatedLabels';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';

import { useDashboardsQuickAccess } from './useDashboardsQuickAccess';

type DashboardsQuickAccessMenuProps = {
  dashboard?: Dashboard;
};

const DashboardsQuickAccessMenu = ({
  dashboard
}: DashboardsQuickAccessMenuProps): ReactElement => {
  const { dashboards } = useDashboardsQuickAccess();

  const { createDashboard } = useDashboardConfig();

  const setIsEditing = useSetAtom(isEditingAtom);

  const navigate = useNavigate();
  const navigateToDashboard = (dashboardId: string | number) => (): void =>
    navigate(
      generatePath(routeMap.dashboard, {
        dashboardId,
        layout: DashboardLayout.Library
      })
    );

  const navigateToDashboardLibrary = (): void => {
    setIsEditing(false);
    navigate(
      generatePath(routeMap.dashboards, { layout: DashboardLayout.Library })
    );
  };

  return (
    <PageLayout.QuickAccess
      create={createDashboard}
      elements={dashboards}
      goBack={navigateToDashboardLibrary}
      isActive={(id) => equals(id, Number(dashboard?.id))}
      labels={{
        create: labelCreateADashboard,
        goBack: labelDashboards
      }}
      navigateToElement={navigateToDashboard}
    />
  );
};

export { DashboardsQuickAccessMenu };
