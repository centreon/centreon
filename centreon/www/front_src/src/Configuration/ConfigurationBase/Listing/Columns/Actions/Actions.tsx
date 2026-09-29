import {
  ContentCopyOutlined as ContentCopyIcon,
  DeleteOutline as DeleteIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { labelDelete, labelDuplicate } from '../../../translatedLabels';
import { useColumnStyles } from '../Columns.styles';
import useActions from './useActions';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useColumnStyles();

  const {
    openDeleteModal,
    openDuplicateModal,
    canDelete,
    canDuplicate,
    rowActions
  } = useActions(row);

  return (
    <Box className={classes.actions}>
      {rowActions.map(({ Icon, dataTestId, label, onClick }) => (
        <IconButton
          ariaLabel={t(label)}
          dataTestid={dataTestId(row)}
          key={label}
          onClick={() => onClick(row)}
          title={t(label)}
        >
          <Icon className={classes.icon} />
        </IconButton>
      ))}
      {canDuplicate && (
        <IconButton
          ariaLabel={t(labelDuplicate)}
          dataTestid={`${labelDuplicate}_${row.id}`}
          onClick={openDuplicateModal}
          title={t(labelDuplicate)}
        >
          <ContentCopyIcon className={classes.duplicateIcon} />
        </IconButton>
      )}
      {canDelete && (
        <IconButton
          ariaLabel={t(labelDelete)}
          className={classes.removeButton}
          dataTestid={`${labelDelete}_${row.id}`}
          onClick={openDeleteModal}
          title={t(labelDelete)}
        >
          <DeleteIcon className={classes.removeIcon} />
        </IconButton>
      )}
    </Box>
  );
};

export default Actions;
