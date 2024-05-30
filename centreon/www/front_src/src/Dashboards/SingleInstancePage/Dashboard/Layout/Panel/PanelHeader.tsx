import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';
import { Link } from 'react-router-dom';
import { useIsFetching } from '@tanstack/react-query';

import { CardHeader, CircularProgress, Typography } from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import DvrIcon from '@mui/icons-material/Dvr';

import { IconButton, useDeepCompare } from '@centreon/ui';

import {
  dashboardAtom,
  duplicatePanelDerivedAtom,
  isEditingAtom
} from '../../atoms';
import {
  labelMoreActions,
  labelResourcesStatus,
  labelSeeMore
} from '../../translatedLabels';

import { usePanelHeaderStyles } from './usePanelStyles';
import MorePanelActions from './MorePanelActions';

interface PanelHeaderProps {
  changeViewMode: (displayType) => void;
  displayMoreActions: boolean;
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
  pageType
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

  const isFetching = useIsFetching({ queryKey: [widgetPrefixQuery] });

  const duplicate = (event): void => {
    event.preventDefault();
    setIsEditing(() => true);
    duplicatePanel(id);
  };

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const page = t(pageType || labelResourcesStatus);

  return (
    <CardHeader
      action={
        displayMoreActions && (
          <div className={classes.panelActionsIcons}>
            {!!isFetching && <CircularProgress size={20} />}
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
              setRefreshCount={setRefreshCount}
            />
          </div>
        )
      }
      className={classes.panelHeader}
      title={
        <Typography className={classes.panelTitle}>
          {panel?.options?.name || ''}
        </Typography>
      }
    />
  );
};

export default PanelHeader;
