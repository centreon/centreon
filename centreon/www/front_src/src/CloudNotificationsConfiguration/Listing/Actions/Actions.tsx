import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { DeleteButton, useDelete } from '../../Actions/delete';
import {
  labelDeleteNotification,
  labelDuplicate
} from '../../translatedLabels';
import { DeleteType } from '../../models';
import { DuplicateButton, useDuplicate } from '../../Actions/duplicate';

import useStyles from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { deleteItems } = useDelete();
  const { duplicateItem } = useDuplicate();

  const onDeleteClick = (): void => {
    deleteItems({
      id: row.id,
      name: row.name,
      type: DeleteType.SingleItem
    });
  };

  const onDuplicateClick = (): void => {
    duplicateItem({ id: row.id });
  };

  return (
    <Box className={classes.actions}>
      <DeleteButton
        ariaLabel={t(labelDeleteNotification) as string}
        iconClassName={classes.icon}
        onClick={onDeleteClick}
      />
      <DuplicateButton
        ariaLabel={t(labelDuplicate) as string}
        className={classes.duplicateicon}
        onClick={onDuplicateClick}
      />
    </Box>
  );
};

export default Actions;
