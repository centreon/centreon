import { useState } from 'react';

import { makeStyles } from 'tss-react/mui';
import { useTranslation } from 'react-i18next';

import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';

import { IconButton } from '@centreon/ui';
import type { ComponentColumnProps } from '@centreon/ui';

import DeleteDialog from '../Dialogs/DeleteDialog';
import { labelDelete } from '../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.error.main,
    fontSize: theme.spacing(2.5)
  }
}));

const DeleteAction = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const [dialogOpen, setDialogOpen] = useState(false);

  const onClick = (): void => setDialogOpen(true);

  const onCancel = (): void => {
    setDialogOpen(false);
  };

  const onConfirm = (): void => {
    setDialogOpen(false);
  };

  return (
    <div>
      <IconButton
        ariaLabel={t(labelDelete)}
        title={t(labelDelete)}
        onClick={onClick}
      >
        <DeleteOutlineIcon className={classes.icon} />
      </IconButton>
      <DeleteDialog
        open={dialogOpen}
        row={row}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </div>
  );
};

export default DeleteAction;
