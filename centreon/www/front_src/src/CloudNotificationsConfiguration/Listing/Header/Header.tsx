import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/material';

import { DeleteButton, useDelete } from '../../Actions/Delete';
import { selectedRowsAtom } from '../../atom';
import { DeleteType } from '../../models';
import { labelDeleteMultipleNotifications } from '../../translatedLabels';

import Add from './Add';
import useStyle from './Header.styles';

const Header = (): JSX.Element => {
  const { classes } = useStyle();
  const { t } = useTranslation();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const { deleteItems } = useDelete();

  const selectedRowsIds = selectedRows?.map((notification) => notification.id);

  const onClick = (): void => {
    deleteItems({
      id: selectedRowsIds,
      type: DeleteType.MultipleItems
    });
  };

  return (
    <Box className={classes.actions}>
      <Add />
      <DeleteButton
        ariaLabel={t(labelDeleteMultipleNotifications) as string}
        className={classes.icon}
        disabled={isEmpty(selectedRows)}
        onClick={onClick}
      />
    </Box>
  );
};

export default Header;
