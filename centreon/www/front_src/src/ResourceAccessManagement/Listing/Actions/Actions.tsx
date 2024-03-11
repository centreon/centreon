import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import { ComponentColumnProps } from '@centreon/ui';

import { DeleteButton } from '../../Actions/Delete';
import { labelDeleteResourceAccessRule } from '../../translatedLabels';
import useDelete from '../../Actions/Delete/useDelete';
import { DeleteType } from '../../models';

import useActionsStyles from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useActionsStyles();
  const { t } = useTranslation();
  const { deleteItems } = useDelete();
  const onDeleteClick = (): void => {
    deleteItems({
      deleteType: DeleteType.SingleItem,
      id: row.id,
      name: row.name
    });
  };

  return (
    <Box className={classes.actions}>
      <DeleteButton
        ariaLabel={t(labelDeleteResourceAccessRule) as string}
        iconClassName={classes.icon}
        onClick={onDeleteClick}
      />
    </Box>
  );
};

export default Actions;
