import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { useSetAtom } from 'jotai';
import { FormikValues, useFormikContext } from 'formik';

import { Box } from '@mui/material';
import DeleteIcon from '@mui/icons-material/DeleteOutline';

import { IconButton } from '@centreon/ui';

import DeleteDialog from '../../../Listing/Dialogs/DeleteDialog';
import { labelDelete } from '../../../translatedLabels';
import { isPanelOpenAtom } from '../../../atom';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.75)
  }
}));

const DeleteAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();

  const [dialogOpen, setDialogOpen] = useState(false);
  const setPanelOpen = useSetAtom(isPanelOpenAtom);

  const {
    values: { name }
  } = useFormikContext<FormikValues>();

  const onClick = (): void => setDialogOpen(true);

  const onCancel = (): void => {
    setDialogOpen(false);
  };

  const onConfirm = (): void => {
    setDialogOpen(false);
    setPanelOpen(false);
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDelete)}
        disabled={false}
        title={t(labelDelete)}
        onClick={onClick}
      >
        <DeleteIcon className={classes.icon} />
      </IconButton>
      <DeleteDialog
        notificationName={name}
        open={dialogOpen}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DeleteAction;
