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
import { useDashboardAccessRights } from '../../components/DashboardLibrary/DashboardAccessRights/useDashboardAccessRights';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './hooks/useDashboardDetails';
import { dashboardAtom, isEditingAtom, refreshCountsAtom } from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import { AddWidgetButton } from './AddEditWidget';
import { editProperties } from './hooks/useCanEditDashboard';
import { useDashboardStyles } from './Dashboard.styles';
import useUnsavedChangesWarning from './hooks/useUnsavedChangesWarning';

const Dashboard = (): ReactElement => {
  const { classes } = useDashboardStyles();

  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardConfig();
  const { editAccessRights } = useDashboardAccessRights();

  const unsavedChangesWarning = useUnsavedChangesWarning({ panels });

  const isEditing = useAtomValue(isEditingAtom);
  const { layout } = useAtomValue(dashboardAtom);
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
          <PageHeader.Message message={unsavedChangesWarning} />
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
                onClick={editAccessRights(dashboard as DashboardType)}
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
        <Layout />
      </PageLayout.Body>
      <DashboardConfigModal showRefreshIntervalFields />
      <DashboardAccessRightsModal />
    </PageLayout>
  );
};

export default Dashboard;
