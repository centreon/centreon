import { useAtomValue, useSetAtom } from 'jotai';

import { CardHeader } from '@mui/material';
import CloseIcon from '@mui/icons-material/Close';
import ContentCopyIcon from '@mui/icons-material/ContentCopy';

import { IconButton } from '@centreon/ui';

import {
  duplicatePanelDerivedAtom,
  isEditingAtom,
  removePanelDerivedAtom
} from '../../atoms';

import usePanelHeaderStyles from './usePanelStyles';

interface PanelHeaderProps {
  id: string;
}

const PanelHeader = ({ id }: PanelHeaderProps): JSX.Element => {
  const { classes } = usePanelHeaderStyles();

  const isEditing = useAtomValue(isEditingAtom);
  const removePanel = useSetAtom(removePanelDerivedAtom);
  const duplicatePanel = useSetAtom(duplicatePanelDerivedAtom);

  const remove = (event): void => {
    event.preventDefault();

    removePanel(id);
  };

  const duplicate = (event): void => {
    event.preventDefault();

    duplicatePanel(id);
  };

  return (
    <CardHeader
      action={
        isEditing && (
          <div className={classes.panelActionsIcons}>
            <IconButton onClick={duplicate}>
              <ContentCopyIcon fontSize="small" />
            </IconButton>
            <IconButton onClick={remove}>
              <CloseIcon fontSize="small" />
            </IconButton>
          </div>
        )
      }
      className={classes.panelHeader}
    />
  );
};

export default PanelHeader;
