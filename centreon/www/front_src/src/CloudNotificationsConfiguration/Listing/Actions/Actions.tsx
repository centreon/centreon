import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import { Box } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { DeleteButton } from '../../Actions';
import { labelDeleteNotification } from '../../translatedLabels';
import { deleteNotificationAtom, isDeleteDialogOpenAtom } from '../../atom';
import { DeleteType } from '../../models';

import Duplicate from './Duplicate';
import useStyles from './Actions.styles';

const Actions = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const setDeleteInformations = useSetAtom(deleteNotificationAtom);
  const setIsDeleteDialog = useSetAtom(isDeleteDialogOpenAtom);

  const onClick = (): void => {
    setDeleteInformations({
      id: row.id,
      name: row.name,
      type: DeleteType.SingleItem
    });
    setIsDeleteDialog(true);
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
