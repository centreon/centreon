import { useState } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';

import { CardHeader } from '@mui/material';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import MoreVertIcon from '@mui/icons-material/MoreVert';

import { IconButton } from '@centreon/ui';

import { duplicatePanelDerivedAtom, isEditingAtom } from '../../atoms';

import { usePanelHeaderStyles } from './usePanelStyles';
import MorePanelActions from './MorePanelActions';

interface PanelHeaderProps {
  id: string;
}

const PanelHeader = ({ id }: PanelHeaderProps): JSX.Element => {
  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const { classes } = usePanelHeaderStyles();

  const isEditing = useAtomValue(isEditingAtom);
  const duplicatePanel = useSetAtom(duplicatePanelDerivedAtom);

  const duplicate = (event): void => {
    event.preventDefault();

    duplicatePanel(id);
  };

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  return (
    <CardHeader
      action={
        isEditing && (
          <div className={classes.panelActionsIcons}>
            <IconButton onClick={duplicate}>
              <ContentCopyIcon fontSize="small" />
            </IconButton>
            <IconButton onClick={openMoreActions}>
              <MoreVertIcon fontSize="small" />
            </IconButton>
            <MorePanelActions
              anchor={moreActionsOpen}
              close={closeMoreActions}
              id={id}
            />
          </div>
        )
      }
      className={classes.panelHeader}
    />
  );
};

export default PanelHeader;
