import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import NotificationsOffIcon from '@mui/icons-material/NotificationsOff';
import { Tooltip } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';

import { labelNotificationDisabled } from '../../translatedLabels';

import IconColumn from './IconColumn';

const useStyles = makeStyles()((theme) => ({
  container: {
    paddingLeft: theme.spacing(0.75)
  }
}));

interface NotificationTooltipProps {
  Icon: (props) => JSX.Element;
  title: string;
}

const NotificationTooltip = ({
  Icon,
  title
}: NotificationTooltipProps): JSX.Element => {
  const icon = <Icon color="primary" fontSize="small" />;

  return <Tooltip title={title}>{icon}</Tooltip>;
};

const NotificationColumn = ({
  row
}: ComponentColumnProps): JSX.Element | null => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  if (row.notification_enabled === false) {
    return (
      <IconColumn>
        <div className={classes.container}>
          <NotificationTooltip
            Icon={NotificationsOffIcon}
            title={t(labelNotificationDisabled)}
          />
        </div>
      </IconColumn>
    );
  }

  return null;
};

export default NotificationColumn;
