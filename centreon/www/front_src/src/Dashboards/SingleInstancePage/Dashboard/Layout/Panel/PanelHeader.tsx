import { useMemo, useState } from 'react';

import { useIsFetching, useQueryClient } from '@tanstack/react-query';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals, isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import { Link } from 'react-router-dom';

import DvrIcon from '@mui/icons-material/Dvr';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import UpdateIcon from '@mui/icons-material/Update';
import {
  Button,
  CardHeader,
  CircularProgress,
  Typography
} from '@mui/material';

import { IconButton, useDeepCompare } from '@centreon/ui';
import { Tooltip } from '@centreon/ui/components';

import {
  dashboardAtom,
  duplicatePanelDerivedAtom,
  isEditingAtom
} from '../../atoms';
import { useLastRefresh } from '../../hooks/useLastRefresh';
import {
  labelMoreActions,
  labelResourcesStatus,
  labelSeeMore
} from '../../translatedLabels';

import MorePanelActions from './MorePanelActions';
import { usePanelHeaderStyles } from './usePanelStyles';

interface PanelHeaderProps {
  changeViewMode: (displayType) => void;
  displayMoreActions: boolean;
  displayShrinkRefresh: boolean;
  forceDisplayShrinkRefresh: boolean;
  id: string;
  linkToResourceStatus?: string;
  pageType: string | null;
  setRefreshCount?: (id) => void;
}

const PanelHeader = ({
  id,
  setRefreshCount,
  linkToResourceStatus,
  displayMoreActions,
  changeViewMode,
  pageType,
  displayShrinkRefresh,
  forceDisplayShrinkRefresh
}: PanelHeaderProps): JSX.Element | null => {
  const { t } = useTranslation();
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const { classes } = usePanelHeaderStyles();

  const dashboard = useAtomValue(dashboardAtom);
  const duplicatePanel = useSetAtom(duplicatePanelDerivedAtom);

  const setIsEditing = useSetAtom(isEditingAtom);

  const panel = useMemo(
    () => dashboard.layout.find((dashbordPanel) => equals(dashbordPanel.i, id)),
    useDeepCompare([dashboard.layout])
  );

  const widgetPrefixQuery = useMemo(
    () => `${panel?.panelConfiguration.path}_${id}`,
    [panel?.panelConfiguration.path, id]
  );

  const queryClient = useQueryClient();
  const isFetching = useIsFetching({ queryKey: [widgetPrefixQuery] });

  const { labelRefresh, isLastRefreshMoreThanADay } =
    useLastRefresh(isFetching);

  const hasQueryData = !isEmpty(
    queryClient.getQueriesData({
      queryKey: [widgetPrefixQuery]
    })
  );

  const duplicate = (event: MouseEvent): void => {
    event.preventDefault();
    setIsEditing(() => true);
    duplicatePanel(id);
  };

  const refresh = (): void => {
    setRefreshCount?.(id);
  };

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const page = t(pageType || labelResourcesStatus);

  return (
    <CardHeader
      action={
        displayMoreActions && (
          <div className={classes.panelActionsIcons}>
            {hasQueryData && (
              <div>
                {forceDisplayShrinkRefresh ||
                (displayShrinkRefresh && isLastRefreshMoreThanADay) ? (
                  <IconButton
                    disabled={!!isFetching}
                    size="small"
                    title={labelRefresh}
                    tooltipPlacement="top"
                    onClick={refresh}
                  >
                    {isFetching ? (
                      <CircularProgress size={22} />
                    ) : (
                      <UpdateIcon sx={{ height: 22, width: 22 }} />
                    )}
                  </IconButton>
                ) : (
                  <Button
                    className={classes.panelHeaderRefreshButton}
                    disabled={!!isFetching}
                    size="small"
                    startIcon={
                      isFetching ? (
                        <CircularProgress size={22} />
                      ) : (
                        <UpdateIcon sx={{ height: 22, width: 22 }} />
                      )
                    }
                    onClick={refresh}
                  >
                    {labelRefresh}
                  </Button>
                )}
              </div>
            )}
            {linkToResourceStatus && (
              <Link
                data-testid={t(labelSeeMore, { page })}
                style={{ all: 'unset' }}
                target="_blank"
                to={linkToResourceStatus as string}
              >
                <IconButton
                  ariaLabel={t(labelSeeMore, { page })}
                  title={t(labelSeeMore, { page })}
                  onClick={changeViewMode}
                >
                  <DvrIcon fontSize="small" />
                </IconButton>
              </Link>
            )}
            <IconButton
              ariaLabel={t(labelMoreActions) as string}
              title={t(labelMoreActions) as string}
              onClick={openMoreActions}
            >
              <MoreHorizIcon fontSize="small" />
            </IconButton>
            <MorePanelActions
              anchor={moreActionsOpen}
              close={closeMoreActions}
              duplicate={duplicate}
              id={id}
            />
          </div>
        )
      }
      className={classes.panelHeader}
      classes={{
        content: displayShrinkRefresh
          ? classes.panelHeaderContentWithShrink
          : classes.panelHeaderContent
      }}
      title={
        <Tooltip
          followCursor={false}
          label={panel?.options?.name}
          placement="top"
        >
          <Typography className={classes.panelTitle}>
            {panel?.options?.name || ''}
          </Typography>
        </Tooltip>
      }
    />
  );
};

export default PanelHeader;
