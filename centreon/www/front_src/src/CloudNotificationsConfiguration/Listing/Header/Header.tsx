import { useAtomValue, useSetAtom } from 'jotai';
import { isEmpty } from 'ramda';

import { Box } from '@mui/material';

import {
  deleteNotificationAtom,
  isDeleteDialogOpenAtom,
  selectedRowsAtom
} from '../../atom';
import { DeleteButton } from '../../Actions';
import { DeleteType } from '../../models';

import Add from './Add';
import useStyle from './Header.styles';

const Header = (): JSX.Element => {
  const { classes } = useStyle();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const setDeleteInformations = useSetAtom(deleteNotificationAtom);
  const setIsDeleteDialog = useSetAtom(isDeleteDialogOpenAtom);

  const selectedRowsIds = selectedRows?.map((notification) => notification.id);

  const onClick = (): void => {
    setDeleteInformations({
      id: selectedRowsIds,
      type: DeleteType.MultipleItems
    });
    setIsDeleteDialog(true);
  };

  return (
    <Box className={classes.actions}>
      <Add />
      <DeleteButton
        ariaLabel="delete multiple notifications"
        className={classes.icon}
        disabled={isEmpty(selectedRows)}
        onClick={onClick}
      />
    </Box>
  );
};

export default Header;
