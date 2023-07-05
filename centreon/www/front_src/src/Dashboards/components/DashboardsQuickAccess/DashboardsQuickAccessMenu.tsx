import { ReactElement } from 'react';

import { generatePath, useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';

import { Add as AddIcon } from '@mui/icons-material';

import { Button, Menu } from '@centreon/ui/components';

import { Dashboard } from '../../api/models';
import routeMap from '../../../reactRoutes/routeMap';
import { labelCreateADashboard } from '../../translatedLabels';
import { useDashboardConfig } from '../DashboardConfig/useDashboardConfig';

import { useDashboardsQuickAccess } from './useDashboardsQuickAccess';

type DashboardsQuickAccessMenuProps = {
  dashboard?: Dashboard;
};

const DashboardsQuickAccessMenu = ({
  dashboard
}: DashboardsQuickAccessMenuProps): ReactElement => {
  const { t } = useTranslation();
  const { dashboards } = useDashboardsQuickAccess();

  const { createDashboard } = useDashboardConfig();

  const navigate = useNavigate();
  const navigateToDashboard = (dashboardId: string | number) => (): void =>
    navigate(generatePath(routeMap.dashboard, { dashboardId }));

  return (
    <Menu>
      <Menu.Button />
      <Menu.Items>
        {dashboards &&
          dashboards.map((d) => (
            <Menu.Item
              key={d.id as string}
              onClick={navigateToDashboard(d.id)}
              {...(dashboard?.id === d.id && {
                isActive: true,
                isDisabled: true
              })}
            >
              {d.name}
            </Menu.Item>
          ))}
        <Menu.Divider key="divider" />
        <Menu.Item key="create">
          <Button
            icon={<AddIcon />}
            iconVariant="start"
            variant="ghost"
            onClick={createDashboard}
          >
            {t(labelCreateADashboard)}
          </Button>
        </Menu.Item>
      </Menu.Items>
    </Menu>
  );
};

export { DashboardsQuickAccessMenu };
