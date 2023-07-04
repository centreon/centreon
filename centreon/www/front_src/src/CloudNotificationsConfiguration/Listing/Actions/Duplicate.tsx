import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import ContentCopyIcon from '@mui/icons-material/ContentCopy';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';
import type { ComponentColumnProps } from '@centreon/ui';

import DuplicateDialog from '../../Dialogs/DuplicateDialog';
import { labelDuplicate } from '../../translatedLabels';

import useStyles from './Actions.styles';

const DuplicateAction = ({ row }: ComponentColumnProps): JSX.Element => {
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
    <Box>
      <IconButton
        ariaLabel={t(labelDuplicate)}
        title={t(labelDuplicate)}
        onClick={onClick}
      >
        <ContentCopyIcon className={classes.duplicateicon} color="primary" />
      </IconButton>
      <DuplicateDialog
        open={dialogOpen}
        row={row}
        onCancel={onCancel}
        onConfirm={onConfirm}
      />
    </Box>
  );
};

export default DuplicateAction;
