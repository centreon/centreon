import { useMemo, useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import { CardHeader } from '@mui/material';
import MoreVertIcon from '@mui/icons-material/MoreVert';

import { IconButton, useDeepCompare } from '@centreon/ui';

import {
  dashboardAtom,
  duplicatePanelDerivedAtom,
  isEditingAtom
} from '../../atoms';
import { labelMoreActions } from '../../translatedLabels';

import { usePanelHeaderStyles } from './usePanelStyles';
import MorePanelActions from './MorePanelActions';

interface PanelHeaderProps {
  id: string;
  setRefreshCount: (id) => void;
}

const PanelHeader = ({
  id,
  setRefreshCount
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
          <IconButton
            ariaLabel={t(labelMoreActions) as string}
            onClick={openMoreActions}
          >
            <MoreVertIcon fontSize="small" />
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
