import {
  FileCopyOutlined as ContentCopyIcon,
  DeleteOutline as DeleteIcon
} from '@mui/icons-material';
import { Box } from '@mui/material';
import { useAtomValue, useSetAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { ComponentColumnProps, IconButton } from '@centreon/ui';

import { userAtom } from '@centreon/ui-context';
import { tokensToDeleteAtom } from '../../../atoms';
import { TokenType } from '../../../models';
import { labelCopy, labelDelete } from '../../../translatedLabels';
import { useStyles } from './Actions.styles';
import useCopyToken from './useCopyToken';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  const { id, canManageApiTokens } = useAtomValue(userAtom);
  const setTokensToDelete = useSetAtom(tokensToDeleteAtom);

  const openDeleteModal = (): void => setTokensToDelete([row]);

  const { copyToken, isLoading } = useCopyToken({
    tokenName: row.name,
    userId: row?.user.id
  });

  const isCopyButtonVisible =
    equals(row.type, TokenType.CMA) &&
    (canManageApiTokens || equals(id, row.creator.id));

  return (
    <Box className={classes.actions}>
      <div>
        {isCopyButtonVisible && (
          <IconButton
            ariaLabel={t(labelCopy)}
            dataTestid={`${labelCopy}_${row.id}`}
            title={t(labelCopy)}
            onClick={copyToken}
            disabled={isLoading}
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
