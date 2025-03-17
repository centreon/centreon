import { useTranslation } from 'react-i18next';

import {
  FileCopyOutlined as ContentCopyIcon,
  DeleteOutline as DeleteIcon
} from '@mui/icons-material';

import { Box } from '@mui/material';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { tokensToDeleteAtom } from '../../../atoms';
import { TokenType } from '../../../models';
import { labelCopy, labelDelete } from '../../../translatedLabels';
import { useStyles } from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const setTokensToDelete = useSetAtom(tokensToDeleteAtom);

  const openDeleteModal = (): void => setTokensToDelete([row]);
  const copyToken = (): void => undefined;

  const isCopyButtonVisible = equals(row.type, TokenType.CMA);

  return (
    <Box className={classes.actions}>
      <div>
        {isCopyButtonVisible && (
          <IconButton
            ariaLabel={t(labelCopy)}
            dataTestid={`${labelCopy}_${row.id}`}
            title={t(labelCopy)}
            onClick={copyToken}
          >
            <ContentCopyIcon className={classes.copyIcon} />
          </IconButton>
        )}
      </div>
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
