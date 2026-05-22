import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { useTranslation } from 'react-i18next';

import { DeleteButton } from '../../Actions/Delete';
import useDelete from '../../Actions/Delete/useDelete';
import { DuplicateButton } from '../../Actions/Duplicate';
import useDuplicate from '../../Actions/Duplicate/useDuplicate';
import { DeleteType, ResourceAccessRule } from '../../models';
import {
  labelDeleteResourceAccessRule,
  labelDuplicate
} from '../../translatedLabels';
import useActionsStyles from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();
  const { deleteItems } = useDelete();
  const { duplicateItem } = useDuplicate();

  const onDeleteClick = (): void => {
    deleteItems({
      deleteType: DeleteType.SingleItem,
      id: row.id as number,
      name: row.name as string
    });
  };

  const onDuplicateClick = (): void => {
    duplicateItem({
      id: row.id as number,
      resourceAccessRule: row as unknown as ResourceAccessRule
    });
  };

  return (
    <Box className={classes.actions}>
      <DeleteButton
        ariaLabel={t(labelDeleteResourceAccessRule) as string}
        iconClassName={classes.icon}
        onClick={onDeleteClick}
      />
      <DuplicateButton
        ariaLabel={t(labelDuplicate) as string}
        className={classes.duplicateIcon}
        onClick={onDuplicateClick}
      />
    </Box>
  );
};

export default Actions;
