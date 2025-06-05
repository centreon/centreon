import { useTranslation } from 'react-i18next';

import {
  ContentCopyOutlined as ContentCopyIcon
  // DeleteOutline as DeleteIcon
} from '@mui/icons-material';

import { Box } from '@mui/material';

import { ComponentColumnProps, DeleteIcon, IconButton } from '@centreon/ui';

import { labelDelete, labelDuplicate } from '../../../translatedLabels';
import { useColumnStyles } from '../Columns.styles';

import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const { openDeleteModal, openDuplicateModal } = useActions(row);

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelDuplicate)}
        dataTestid={`${labelDuplicate}_${row.id}`}
        title={t(labelDuplicate)}
        onClick={openDuplicateModal}
      >
        <ContentCopyIcon className={classes.duplicateIcon} />
      </IconButton>
      <IconButton
        ariaLabel={t(labelDelete)}
        dataTestid={`${labelDelete}_${row.id}`}
        title={t(labelDelete)}
        onClick={openDeleteModal}
        className={classes.removeButton}
      >
        {/* <DeleteIcon className={classes.removeIcon} /> */}
        <DeleteIcon />
      </IconButton>
    </Box>
  );
};

export default Actions;
