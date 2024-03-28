import { useTranslation } from 'react-i18next';
import { useSetAtom } from 'jotai';

import { Delete as DeleteIcon } from '@mui/icons-material';

import { IconButton } from '@centreon/ui';

import { labelDelete } from '../../../../../translatedLabels';
import { useColumnStyles } from '../useColumnStyles';
import { dashboardToDeleteAtom } from '../../../../../atoms';
import { FormattedDashboard } from '../../../../../api/models';
import { unformatDashboard } from '../../utils';

interface Props {
  row: FormattedDashboard;
}
const DeleteDashboard = ({ row }: Props): JSX.Element => {
  const { classes } = useColumnStyles();
  const { t } = useTranslation();

  const setDashboardToDelete = useSetAtom(dashboardToDeleteAtom);

  const openDeleteModal = (): void => {
    setDashboardToDelete(unformatDashboard(row));
  };

  return (
    <IconButton
      ariaLabel={t(labelDelete)}
      key={labelDelete}
      title={t(labelDelete)}
      onClick={openDeleteModal}
    >
      <DeleteIcon className={classes.icon} />
    </IconButton>
  );
};

export default DeleteDashboard;
