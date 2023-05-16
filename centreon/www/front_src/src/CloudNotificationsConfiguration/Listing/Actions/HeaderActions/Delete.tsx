import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';

import { Box } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import { IconButton } from '@centreon/ui';

import { selectedRowsAtom } from '../../../atom';
import DeleteDialog from '../../Dialogs/DeleteDialog';
import { labelDelete } from '../../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary
  }
}));

const DeleteAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();

  const [dialogOpen, setDialogOpen] = useState(false);
  const selected = useAtomValue(selectedRowsAtom);

  const onClick = (): void => {
    setDialogOpen(true);
  };

  const onCancel = (): void => {
    setDialogOpen(false);
  };

  const onConfirm = (): void => {
    setDialogOpen(false);
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDelete)}
        className={classes.icon}
        disabled={isEmpty(selected)}
        title={t(labelDelete)}
        onClick={onClick}
      >
        <DeleteIcon />
      </IconButton>
      <DeleteDialog
        open={dialogOpen}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DeleteAction;
