import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import DuplicateIcon from '@mui/icons-material/ContentCopy';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';

import DuplicateDialog from '../../Listing/Dialogs/DuplicateDialog';
import { labelDuplicate } from '../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary,
    fontSize: theme.spacing(2.25)
  }
}));

const DuplicateAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();

  const [openDuplicateDialog, setOpenDuplicateDialog] = useState(false);

  const onDuplicateActionClick = (): void => setOpenDuplicateDialog(true);

  const onDuplicateActionCancel = (): void => {
    setOpenDuplicateDialog(false);
  };

  const onDuplicateActionConfirm = (): void => {
    setOpenDuplicateDialog(false);
  };

  return (
    <Box>
      <IconButton
        ariaLabel={t(labelDuplicate)}
        disabled={false}
        title={t(labelDuplicate)}
        onClick={onDuplicateActionClick}
      >
        <DuplicateIcon className={classes.icon} />
      </IconButton>
      <DuplicateDialog
        open={openDuplicateDialog}
        onCancel={onDuplicateActionCancel}
        onConfirm={onDuplicateActionConfirm}
      />
    </Box>
  );
};

export default DuplicateAction;
