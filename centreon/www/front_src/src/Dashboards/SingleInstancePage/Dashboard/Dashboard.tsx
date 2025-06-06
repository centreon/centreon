import { type ReactElement, useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { inc } from 'ramda';

import {
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';
import RefreshIcon from '@mui/icons-material/Refresh';
import { Divider } from '@mui/material';

import { IconButton, PageHeader, PageLayout } from '@centreon/ui/components';

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { type Dashboard as DashboardType, resource } from '../../api/models';
import { isSharesOpenAtom } from '../../atoms';
import { DashboardAccessRightsModal } from '../../components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import { DashboardConfigModal } from '../../components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import { useDashboardConfig } from '../../components/DashboardLibrary/DashboardConfig/useDashboardConfig';
import FavoriteAction from '../../components/DashboardLibrary/DashboardListing/Actions/favoriteAction';
import { DashboardsQuickAccessMenu } from '../../components/DashboardLibrary/DashboardsQuickAccess/DashboardsQuickAccessMenu';
import DashboardNavbar from '../../components/DashboardNavbar/DashboardNavbar';
import { AddWidgetButton } from './AddEditWidget';
import { useDashboardStyles } from './Dashboard.styles';
import Layout from './Layout';
import { dashboardAtom, isEditingAtom, refreshCountsAtom } from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import DashboardSaveBlockerModal from './components/DashboardSaveBlockerModal';
import DeleteWidgetModal from './components/DeleteWidgetModal';
import { useCanEditProperties } from './hooks/useCanEditDashboard';
import useDashboardDetails, { routerParams } from './hooks/useDashboardDetails';

const Dashboard = (): ReactElement => {
  const { classes } = useDashboardStyles();

  const { dashboardId } = routerParams.useParams();
  const queryClient = useQueryClient();
  const isFetchingListing = useIsFetching({ queryKey: [resource.dashboards] });

  const { dashboard, panels, refetch } = useDashboardDetails({
    dashboardId: dashboardId as string
  });
  const { editDashboard } = useDashboardConfig();

  const isEditing = useAtomValue(isEditingAtom);
  const { layout } = useAtomValue(dashboardAtom);
  const setRefreshCounts = useSetAtom(refreshCountsAtom);
  const setIsSharesOpen = useSetAtom(isSharesOpenAtom);

  const { canEdit } = useCanEditProperties();

  const refreshIframes = () => {
    const iframes = document.querySelectorAll(
      'iframe[title="Webpage Display"]'
    );

    iframes.forEach((iframe) => {
      // biome-ignore lint/correctness/noSelfAssign: <explanation>
      iframe.src = iframe.src;
    });
  };

  const refreshAllWidgets = (): void => {
    refreshIframes();

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

  const updateFavorites = (): void => {
    refetch?.();
    queryClient.invalidateQueries({ queryKey: [resource.dashboards] });
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
              actions={
                <FavoriteAction
                  dashboardId={dashboard?.id as number}
                  isFavorite={dashboard?.isFavorite as boolean}
                  refetch={updateFavorites}
                  isFetching={isFetchingListing > 0}
                />
              }
            />
          </PageHeader.Main>
          <DashboardNavbar />
        </PageHeader>
      </PageLayout.Header>
      <PageLayout.Body>
        <div className={classes.body}>
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
        </div>
        <Layout />
      </PageLayout.Body>
      <DashboardConfigModal showRefreshIntervalFields />
      <DashboardAccessRightsModal />
      <DeleteWidgetModal />
      <DashboardSaveBlockerModal panels={panels} />
    </PageLayout>
  );
};

export default Dashboard;
