import { useTranslation } from 'react-i18next';

import {
  FileCopyOutlined as ContentCopyIcon,
  DeleteOutline as DeleteIcon
} from '@mui/icons-material';

import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { tokensToDeleteAtom } from '../../../atoms';
import { labelCopy, labelDelete } from '../../../translatedLabels';
import { useStyles } from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const setTokensToDelete = useSetAtom(tokensToDeleteAtom);

  const openDeleteModal = (): void => setTokensToDelete([row]);
  const copyToken = (): void => undefined;

  return (
    <Box className={classes.actions}>
      <IconButton
        ariaLabel={t(labelCopy)}
        dataTestid={`${labelCopy}_${row.id}`}
        title={t(labelCopy)}
        onClick={copyToken}
      >
        <ContentCopyIcon className={classes.copyIcon} />
      </IconButton>
      <IconButton
        ariaLabel={t(labelDelete)}
        dataTestid={`${labelDelete}_${row.id}`}
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
