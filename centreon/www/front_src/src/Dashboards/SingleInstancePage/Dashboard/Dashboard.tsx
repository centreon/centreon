import { ReactElement, useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { inc, map, uniq } from 'ramda';

import {
  Settings as SettingsIcon,
  Share as ShareIcon,
  FileUploadOutlined as ExportIcon
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
import useExportPDF from '../../hooks/useExportPDF';

import Layout from './Layout';
import useDashboardDetails, { routerParams } from './hooks/useDashboardDetails';
import { dashboardAtom, isEditingAtom, refreshCountsAtom } from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import { AddWidgetButton } from './AddEditWidget';
import { useCanEditProperties } from './hooks/useCanEditDashboard';
import { useDashboardStyles } from './Dashboard.styles';
import DeleteWidgetModal from './components/DeleteWidgetModal';
import DashboardSaveBlockerModal from './components/DashboardSaveBlockerModal';

const Dashboard = ({ id }: { id?: string }): ReactElement => {
  const { classes } = useDashboardStyles();

  const { dashboardId } = routerParams.useParams();
  const { dashboard, panels } = useDashboardDetails({
    dashboardId: dashboardId || (id as string)
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

  const contentElement = document.getElementById('dashboard-content');

  const rect = contentElement?.getBoundingClientRect();

  const topDashbaordY = rect?.y || 0;
  const dashboardHeight = rect?.height || 1;

  const widgets = contentElement?.getElementsByClassName('react-grid-item');

  const widgetsCoordinates = uniq(
    map(
      (widget) =>
        (widget?.getBoundingClientRect()?.y +
          widget?.getBoundingClientRect()?.height -
          topDashbaordY) /
        dashboardHeight,
      widgets || []
    )
  );

  const { exportPDf: asyncExportPDf } = useExportPDF({
    description: dashboard?.description || '',
    exportOptions: { filename: 'dashboard.pdf' },
    name: dashboard?.name || '',
    targetElm: contentElement,
    widgetsCoordinates
  });

  const exportPDf = async () => {
    await asyncExportPDf();
  };

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
              <IconButton
                aria-label="export"
                data-testid="export"
                icon={<ExportIcon />}
                size="small"
                variant="primary"
                onClick={exportPDf}
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
