import { useTranslation } from 'react-i18next';

import {
  ContentCopy as ContentCopyIcon,
  DeleteOutlined as DeleteIcon
} from '@mui/icons-material';

import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { labelDelete, labelDuplicate } from '../../../translatedLabels';
import { useColumnStyles } from '../ColumnsStyles';

import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const { openDeleteModal, openDuplicateModal } = useActions(row);

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelDuplicate)}
        title={t(labelDuplicate)}
        onClick={openDuplicateModal}
      >
        <ContentCopyIcon className={classes.duplicateIcon} />
      </IconButton>
      <IconButton
        ariaLabel={t(labelDelete)}
        title={t(labelDelete)}
        onClick={openDeleteModal}
        className={classes.removeButton}
      >
        <DeleteIcon className={classes.removeIcon} />
      </IconButton>
    </Box>
  );
};

export default Actions;
