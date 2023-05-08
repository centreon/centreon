import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import DuplicateIcon from '@mui/icons-material/ContentCopy';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';

import DuplicateDialog from '../../../Listing/Dialogs/DuplicateDialog';
import { labelDuplicate } from '../../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.25)
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
        ariaLabel={t(labelDuplicate)}
        disabled={false}
        title={t(labelDuplicate)}
        onClick={onClick}
      >
        <DuplicateIcon className={classes.icon} />
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
