import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import DuplicateIcon from '@mui/icons-material/ContentCopy';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';

import DuplicateDialog from '../../../Dialogs/DuplicateDialog';
import { labelDuplicate } from '../../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    fontSize: theme.spacing(2)
  }
}));

const DuplicateAction = (): JSX.Element => {
  const { classes } = useStyle();

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
    <Box>
      <IconButton
        disabled
        ariaLabel={t(labelDuplicate)}
        title={t(labelDuplicate)}
        onClick={onClick}
      >
        <DuplicateIcon className={classes.icon} color="disabled" />
      </IconButton>
      <DuplicateDialog
        open={dialogOpen}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DuplicateAction;
