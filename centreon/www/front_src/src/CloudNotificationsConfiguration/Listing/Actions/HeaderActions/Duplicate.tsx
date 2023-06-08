import { useState } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { useAtom } from 'jotai';
import { isEmpty } from 'ramda';

import DuplicateIcon from '@mui/icons-material/ContentCopy';
import { Box } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { selectedRowsAtom } from '../../../atom';
import DuplicateDialog from '../../Dialogs/DuplicateDialog';
import { labelDuplicate } from '../../../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  icon: {
    color: theme.palette.text.secondary
  }
}));

const DuplicateAction = (): JSX.Element => {
  const { classes } = useStyle();

  const { t } = useTranslation();

  const [dialogOpen, setDialogOpen] = useState(false);
  const [selected] = useAtom(selectedRowsAtom);

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
        className={classes.icon}
        disabled={isEmpty(selected)}
        title={t(labelDuplicate)}
        onClick={onClick}
      >
        <DuplicateIcon />
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
