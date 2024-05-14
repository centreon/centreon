import { ReactElement, useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { inc } from 'ramda';

import {
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';
import { Divider } from '@mui/material';
import RefreshIcon from '@mui/icons-material/Refresh';

import { IconButton, PageHeader, PageLayout } from '@centreon/ui/components';

import { DashboardsQuickAccessMenu } from '../../components/DashboardLibrary/DashboardsQuickAccess/DashboardsQuickAccessMenu';
import { DashboardConfigModal } from '../../components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import { useDashboardConfig } from '../../components/DashboardLibrary/DashboardConfig/useDashboardConfig';
import { Dashboard as DashboardType } from '../../api/models';
import { DashboardAccessRightsModal } from '../../components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import { isSharesOpenAtom } from '../../atoms';
import DashboardNavbar from '../../components/DashboardNavbar/DashboardNavbar';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './hooks/useDashboardDetails';
import { dashboardAtom, isEditingAtom, refreshCountsAtom } from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import { AddWidgetButton } from './AddEditWidget';
import { useCanEditProperties } from './hooks/useCanEditDashboard';
import { useDashboardStyles } from './Dashboard.styles';
import DeleteWidgetModal from './components/DeleteWidgetModal';
import DashboardSaveBlockerModal from './components/DashboardSaveBlockerModal';

const Dashboard = (): ReactElement => {
  const { classes } = useDashboardStyles();

  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardConfig();

  const isEditing = useAtomValue(isEditingAtom);
  const { layout } = useAtomValue(dashboardAtom);
  const setRefreshCounts = useSetAtom(refreshCountsAtom);
  const setIsSharesOpen = useSetAtom(isSharesOpenAtom);

  const { canEdit } = useCanEditProperties();

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

  const openAccessRights = (): void => {
    setIsSharesOpen(dashboard as DashboardType);
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
          <DashboardNavbar />
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <PageLayout.Actions rowReverse={isEditing}>
          {!isEditing && canEdit && (
            <span>
              <IconButton
                aria-label="edit"
                data-testid="edit"
                icon={<SettingsIcon />}
                size="small"
                variant="primary"
                onClick={editDashboard(dashboard as DashboardType)}
              />
              <IconButton
                aria-label="share"
                data-testid="share"
                icon={<ShareIcon />}
                size="small"
                variant="primary"
                onClick={openAccessRights}
              />
              <IconButton
                aria-label="refresh"
                data-testid="refresh"
                icon={<RefreshIcon />}
                size="small"
                variant="primary"
                onClick={refreshAllWidgets}
              />
            </span>
          )}
          {canEdit && (
            <div className={classes.editActions}>
              <AddWidgetButton />
              {isEditing && (
                <Divider
                  className={classes.divider}
                  orientation="vertical"
                  variant="middle"
                />
              )}
              <DashboardEditActions panels={panels} />
            </div>
          )}
        </PageLayout.Actions>
        <Layout dashboardId={dashboardId} />
      </PageLayout.Body>
      <DashboardConfigModal showRefreshIntervalFields />
      <DashboardAccessRightsModal />
      <DeleteWidgetModal />
      <DashboardSaveBlockerModal panels={panels} />
    </PageLayout>
  );
};

export default Dashboard;
