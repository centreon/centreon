// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import {
  FullscreenExit as FullscreenExitIcon,
  Fullscreen as FullscreenIcon,
  Settings as SettingsIcon,
  Share as ShareIcon
} from '@mui/icons-material';
import RefreshIcon from '@mui/icons-material/Refresh';
import {
  Divider,
  IconButton as MuiIconButton,
  Typography
} from '@mui/material';

import { PageLayout } from '@centreon/ui/components';

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { inc } from 'ramda';
import { type ReactElement, useEffect, useState } from 'react';

import { type Dashboard as DashboardType, resource } from '../../api/models';
import { isSharesOpenAtom } from '../../atoms';
import { DashboardAccessRightsModal } from '../../components/DashboardLibrary/DashboardAccessRights/DashboardAccessRightsModal';
import { DashboardConfigModal } from '../../components/DashboardLibrary/DashboardConfig/DashboardConfigModal';
import { useDashboardConfig } from '../../components/DashboardLibrary/DashboardConfig/useDashboardConfig';
import FavoriteAction from '../../components/DashboardLibrary/DashboardListing/Actions/favoriteAction';
import DashboardNavbar from '../../components/DashboardNavbar/DashboardNavbar';
import { AddWidgetButton } from './AddEditWidget';
import { dashboardAtom, isEditingAtom, refreshCountsAtom } from './atoms';
import { DashboardEditActions } from './components/DashboardEdit/DashboardEditActions';
import DashboardSaveBlockerModal from './components/DashboardSaveBlockerModal';
import DeleteWidgetModal from './components/DeleteWidgetModal';
import { useDashboardStyles } from './Dashboard.styles';
import { useCanEditProperties } from './hooks/useCanEditDashboard';
import useDashboardDetails, { routerParams } from './hooks/useDashboardDetails';
import Layout from './Layout';

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

  const [isFullscreen, setIsFullscreen] = useState(
    Boolean(document.fullscreenElement)
  );

  useEffect(() => {
    const onFullscreenChange = (): void => {
      setIsFullscreen(Boolean(document.fullscreenElement));
    };

    document.addEventListener('fullscreenchange', onFullscreenChange);

    return () =>
      document.removeEventListener('fullscreenchange', onFullscreenChange);
  }, []);

  const toggleFullscreen = (): void => {
    if (document.fullscreenElement) {
      document.exitFullscreen?.();

      return;
    }
    document.getElementById('page')?.requestFullscreen?.();
  };

  const refreshIframes = () => {
    const iframes = document.querySelectorAll(
      'iframe[title="Webpage Display"]'
    );

    iframes.forEach((iframe) => {
      // biome-ignore lint/correctness/noSelfAssign: false positive
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
        <div className={classes.headerRow}>
          <div className={classes.titleGroup}>
            <Typography
              aria-label="page header title"
              className={classes.titleText}
              variant="h1"
            >
              {dashboard?.name || ''}
            </Typography>
            {dashboard?.description && (
              <>
                <Divider
                  className={classes.titleSeparator}
                  flexItem
                  orientation="vertical"
                />
                <Typography
                  aria-label="page header description"
                  className={classes.titleDescription}
                  title={dashboard.description}
                  variant="body2"
                >
                  {dashboard.description}
                </Typography>
              </>
            )}
          </div>
          <div
            className={classes.headerActionsRow}
            data-fullscreen={isFullscreen}
          >
            <span>
              {!isEditing && canEdit && (
                <MuiIconButton
                  aria-label="refresh"
                  className={classes.headerActionButton}
                  data-testid="refresh"
                  onClick={refreshAllWidgets}
                  size="small"
                >
                  <RefreshIcon fontSize="small" />
                </MuiIconButton>
              )}
              {!isEditing && (
                <span className={classes.headerActionButton}>
                  <FavoriteAction
                    dashboardId={dashboard?.id as number}
                    isFavorite={dashboard?.isFavorite as boolean}
                    isFetching={isFetchingListing > 0}
                    refetch={updateFavorites}
                  />
                </span>
              )}
              {!isEditing && canEdit && (
                <>
                  <MuiIconButton
                    aria-label="share"
                    className={classes.headerActionButton}
                    data-testid="share"
                    onClick={openAccessRights}
                    size="small"
                  >
                    <ShareIcon fontSize="small" />
                  </MuiIconButton>
                  <MuiIconButton
                    aria-label="edit"
                    className={classes.headerActionButton}
                    data-testid="edit"
                    onClick={editDashboard(dashboard as DashboardType)}
                    size="small"
                  >
                    <SettingsIcon fontSize="small" />
                  </MuiIconButton>
                  <MuiIconButton
                    aria-label="fullscreen"
                    className={classes.headerActionButton}
                    data-testid="fullscreen"
                    onClick={toggleFullscreen}
                    size="small"
                  >
                    {isFullscreen ? (
                      <FullscreenExitIcon fontSize="small" />
                    ) : (
                      <FullscreenIcon fontSize="small" />
                    )}
                  </MuiIconButton>
                </>
              )}
            </span>
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
          </div>
        </div>
        <DashboardNavbar />
      </PageLayout.Header>
      <PageLayout.Body>
        <div className={classes.body}>
          <Layout />
        </div>
      </PageLayout.Body>
      <DashboardConfigModal showRefreshIntervalFields />
      <DashboardAccessRightsModal />
      <DeleteWidgetModal />
      <DashboardSaveBlockerModal panels={panels} />
    </PageLayout>
  );
};

export default Dashboard;
