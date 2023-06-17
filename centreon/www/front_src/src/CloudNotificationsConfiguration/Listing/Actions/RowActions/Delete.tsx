import { makeStyles } from 'tss-react/mui';

import { Method, ComponentColumnProps } from '@centreon/ui';

import DeleteButton from '../../../Actions/DeleteButton';
import {
  labelFailedToDeleteNotification,
  labelNotificationSuccessfullyDeleted
} from '../../../translatedLabels';
import { notificationEndpoint } from '../../../EditPanel/api/endpoints';

const useStyle = makeStyles()((theme) => ({
  icon: {
    '&:hover': {
      color: theme.palette.error.main
    },
    color: theme.palette.primary.main,
    fontSize: theme.spacing(2.5)
  }
}));

const DeleteAction = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyle();

  const getEndpoint = (): string => notificationEndpoint({ id: row.id });

  return (
    <DeleteButton
      ariaLabel="delete a notification"
      fetchMethod={Method.DELETE}
      getEndpoint={getEndpoint}
      iconClassName={classes.icon}
      labelFailed={labelFailedToDeleteNotification}
      labelSuccess={labelNotificationSuccessfullyDeleted}
      notificationName={row.name}
    />
  );
};

export default DeleteAction;
