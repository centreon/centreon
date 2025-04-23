import { useAtomValue } from 'jotai';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import { Box } from '@mui/system';

import { DeleteButton } from '../../Actions/Delete';
import useDelete from '../../Actions/Delete/useDelete';
import { selectedRowsAtom } from '../../atom';
import { DeleteType } from '../../models';
import { labelDeleteMultipleResourceAccessRules } from '../../translatedLabels';

import AddButton from './AddButton';
import useHeaderStyles from './Header.styles';

const Header = (): JSX.Element => {
  const { classes } = useHeaderStyles();
  const { t } = useTranslation();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const { deleteItems, openDialog } = useDelete();
  const selectedRowsIds = selectedRows?.map((rule) => rule.id);

  const onClick = (): void => {
    openDialog();
    deleteItems({
      deleteType: DeleteType.MultipleItems,
      id: selectedRowsIds
    });
  };

  return (
    <Box className={classes.actions}>
      <AddButton />
      <DeleteButton
        ariaLabel={t(labelDeleteMultipleResourceAccessRules) as string}
        className={classes.icon}
        disabled={isEmpty(selectedRowsIds)}
        onClick={onClick}
      />
    </Box>
  );
};

export default Header;
