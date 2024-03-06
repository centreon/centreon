import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals, includes } from 'ramda';
import { Link } from 'react-router-dom';

import { CardHeader } from '@mui/material';
import MoreHorizIcon from '@mui/icons-material/MoreHoriz';
import DvrIcon from '@mui/icons-material/Dvr';

import { IconButton, useDeepCompare } from '@centreon/ui';

import {
  dashboardAtom,
  duplicatePanelDerivedAtom,
  isEditingAtom
} from '../../atoms';
import { labelMoreActions, labelSeeMore } from '../../translatedLabels';
import { resourceBasedWidgets } from '../../utils';

import { usePanelHeaderStyles } from './usePanelStyles';
import MorePanelActions from './MorePanelActions';

interface PanelHeaderProps {
  changeViewMode: (displayType) => void;
  id: string;
  linkToResourceStatus: string;
  setRefreshCount: (id) => void;
  widgetName: string;
}

const PanelHeader = ({
  id,
  setRefreshCount,
  linkToResourceStatus,
  widgetName,
  changeViewMode
}: PanelHeaderProps): JSX.Element => {
  const { t } = useTranslation();

  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const { classes } = usePanelHeaderStyles();

  const dashboard = useAtomValue(dashboardAtom);
  const duplicatePanel = useSetAtom(duplicatePanelDerivedAtom);

  const setIsEditing = useSetAtom(isEditingAtom);

  const duplicate = (event): void => {
    event.preventDefault();
    setIsEditing(true);
    duplicatePanel(id);
  };

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const panel = useMemo(
    () => dashboard.layout.find((dashbordPanel) => equals(dashbordPanel.i, id)),
    useDeepCompare([dashboard.layout])
  );

  return (
    <CardHeader
      action={
        <div className={classes.panelActionsIcons}>
          {includes(widgetName, resourceBasedWidgets) && (
            <Link
              data-testid={labelSeeMore}
              style={{ all: 'unset' }}
              target="_blank"
              to={linkToResourceStatus as string}
            >
              <IconButton
                ariaLabel={t(labelSeeMore) as string}
                title={t(labelSeeMore) as string}
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
      }
      className={classes.panelHeader}
      title={panel?.options?.name || ''}
    />
  );
};

export default PanelHeader;
