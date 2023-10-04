import { ReactElement, useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { equals, inc } from 'ramda';

import {
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';
import RefreshIcon from '@mui/icons-material/Refresh';

import { IconButton, PageHeader, PageLayout } from '@centreon/ui/components';

import { DashboardsQuickAccessMenu } from '../components/DashboardsQuickAccess/DashboardsQuickAccessMenu';
import { DashboardConfigModal } from '../components/DashboardConfig/DashboardConfigModal';
import { useDashboardConfig } from '../components/DashboardConfig/useDashboardConfig';
import { Dashboard as DashboardType } from '../api/models';
import { DashboardAccessRightsModal } from '../components/DashboardAccessRights/DashboardAccessRightsModal';
import { useDashboardAccessRights } from '../components/DashboardAccessRights/useDashboardAccessRights';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './useDashboardDetails';
import {
  dashboardAtom,
  dashboardRefreshIntervalAtom,
  isEditingAtom,
  refreshCountsAtom
} from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import { AddWidgetButton } from './AddEditWidget';
import { editProperties } from './useCanEditDashboard';

const Dashboard = (): ReactElement => {
  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardConfig();
  const { editAccessRights } = useDashboardAccessRights();

  const isEditing = useAtomValue(isEditingAtom);
  const { layout } = useAtomValue(dashboardAtom);
  const dashboardRefreshInterval = useAtomValue(dashboardRefreshIntervalAtom);
  const setRefreshCounts = useSetAtom(refreshCountsAtom);

  const { canEdit } = editProperties.useCanEditProperties();

  const refreshAllWidgets = (): void => {
    setRefreshCounts((prev) => {
      return layout.reduce((acc, widget) => {
        const prevRefreshCount = prev[widget.i];

        return {
          ...acc,
          [widget.i]: inc(prevRefreshCount || 0)
        };
      }, {});
    });
  };

  useEffect(() => {
    return () => {
      setRefreshCounts({});
    };
  }, []);

  return (
    <PageLayout>
      <PageLayout.Header>
        <PageHeader>
          <PageHeader.Main>
            <PageHeader.Menu>
              <DashboardsQuickAccessMenu dashboard={dashboard} />
            </PageHeader.Menu>
            <PageHeader.Title
              description={dashboard?.description || ''}
              title={dashboard?.name || ''}
            />
          </PageHeader.Main>
          <PageHeader.Actions>
            {canEdit && <DashboardEditActions panels={panels} />}
          </PageHeader.Actions>
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <PageLayout.Actions>
          <span>
            {!isEditing && canEdit && (
              <>
                <IconButton
                  aria-label="edit"
                  data-testid="edit"
                  icon={<SettingsIcon />}
                  size="small"
                  variant="ghost"
                  onClick={editDashboard(dashboard as DashboardType)}
                />
                <IconButton
                  aria-label="share"
                  data-testid="share"
                  icon={<ShareIcon />}
                  size="small"
                  variant="ghost"
                  onClick={editAccessRights(dashboard as DashboardType)}
                />
                <IconButton
                  aria-label="refresh"
                  data-testid="refresh"
                  icon={<RefreshIcon />}
                  size="small"
                  variant="ghost"
                  onClick={refreshAllWidgets}
                />
              </>
            )}
          </span>
          <span>{canEdit && <AddWidgetButton />}</span>
        </PageLayout.Actions>

        <Layout />
      </PageLayout.Body>
      <DashboardConfigModal />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export { Dashboard };
