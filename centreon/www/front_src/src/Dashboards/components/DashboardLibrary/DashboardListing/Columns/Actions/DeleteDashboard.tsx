import { useTranslation } from 'react-i18next';

import { Delete as DeleteIcon } from '@mui/icons-material';

import { ConfirmationTooltip } from '@centreon/ui/components';
import { IconButton } from '@centreon/ui';

import {
  labelDescriptionDeleteDashboard,
  labelCancel,
  labelDelete
} from '../../../../../translatedLabels';
import { useColumnStyles } from '../useColumnStyles';

interface Props {
  dashboardName: string;
  deleteDashboard: () => void;
}
const DeleteDashboard = ({
  dashboardName,
  deleteDashboard
}: Props): JSX.Element => {
  const { classes } = useColumnStyles();
  const { t } = useTranslation();

  const labelsDelete = {
    cancel: t(labelCancel),
    confirm: {
      label: t(labelDelete),
      secondaryLabel: t(labelDescriptionDeleteDashboard, {
        name: dashboardName
      })
    }
  };

  return (
    <ConfirmationTooltip
      confirmVariant="error"
      labels={labelsDelete}
      onConfirm={deleteDashboard}
    >
      {(openTooltip) => (
        <IconButton
          ariaLabel={t(labelDelete)}
          key={labelDelete}
          title={t(labelDelete)}
          onClick={openTooltip}
        >
          <DeleteIcon className={classes.icon} />
        </IconButton>
      )}
    </ConfirmationTooltip>
  );
};

export default DeleteDashboard;
