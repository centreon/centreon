import { useTranslation } from 'react-i18next';

import {
  ContentCopyOutlined as ContentCopyIcon,
  DeleteOutline as DeleteIcon
} from '@mui/icons-material';

import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { labelDelete, labelDuplicate } from '../../../translatedLabels';
import { useColumnStyles } from '../Columns.styles';

import { JSX } from 'react';
import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const { openDeleteModal, openDuplicateModal, canDelete, canDuplicate } =
    useActions(row);

  return (
    <Box className={classes.actions}>
      {canDuplicate && (
        <IconButton
          ariaLabel={t(labelDuplicate)}
          dataTestid={`${labelDuplicate}_${row.id}`}
          title={t(labelDuplicate)}
          onClick={openDuplicateModal}
        >
          <ContentCopyIcon className={classes.duplicateIcon} />
        </IconButton>
      )}
      {canDelete && (
        <IconButton
          ariaLabel={t(labelDelete)}
          dataTestid={`${labelDelete}_${row.id}`}
          title={t(labelDelete)}
          onClick={openDeleteModal}
          className={classes.removeButton}
        >
          <DeleteIcon className={classes.removeIcon} />
        </IconButton>
      )}
    </Box>
  );
};

export default Actions;
