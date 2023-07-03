import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { DeleteButton, useDelete } from '../../Actions';
import { labelDeleteNotification } from '../../translatedLabels';
import { DeleteType } from '../../models';

import Duplicate from './Duplicate';
import useStyles from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const { deleteItems } = useDelete();

  const onClick = (): void => {
    deleteItems({
      id: row.id,
      name: row.name,
      type: DeleteType.SingleItem
    });
  };

  return (
    <Box className={classes.actions}>
      <DeleteButton
        ariaLabel={t(labelDeleteNotification) as string}
        iconClassName={classes.icon}
        onClick={onClick}
      />
      <Duplicate row={row} />
    </Box>
  );
};

export default Actions;
