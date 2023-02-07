import { makeStyles } from 'tss-react/mui';
import { CSSObject } from 'tss-react';

import StorageIcon from '@mui/icons-material/Storage';
import LatencyIcon from '@mui/icons-material/Speed';
import { Avatar } from '@mui/material';

import { getStatusColors, SeverityCode } from '@centreon/ui';

export interface PollerStatusIconProps {
  iconSeverities: {
    database: {
      label: string;
      severity: SeverityCode;
    };
    latency: {
      label: string;
      severity: SeverityCode;
    };
  };
}

interface StyleProps {
  databaseSeverity: SeverityCode;
  latencySeverity: SeverityCode;
}

const useStatusStyles = makeStyles<StyleProps>()(
  (theme, { databaseSeverity, latencySeverity }) => {
    const getSeverityColor = (severityCode): CSSObject => ({
      background: getStatusColors({
        severityCode,
        theme
      }).backgroundColor,
      color: getStatusColors({
        severityCode,
        theme
      }).color
    });

    return {
      avatar: {
        fontSize: theme.typography.body1.fontSize,
        height: theme.spacing(2.125),
        width: theme.spacing(2.125)
      },
      container: {
        display: 'flex',
        gap: theme.spacing(0.5),
        [theme.breakpoints.up(768)]: {
          minHeight: theme.spacing(0.3)
        },

        [theme.breakpoints.down(768)]: {
          bottom: 0,
          flexFlow: 'column wrap',
          gap: theme.spacing(0.3),
          right: theme.spacing(1)
        }
      },
      database: getSeverityColor(databaseSeverity),
      icon: {
        height: theme.spacing(1.75),
        width: theme.spacing(1.75)
      },
      latency: getSeverityColor(latencySeverity)
    };
  }
);

const PollerStatusIcon = ({
  iconSeverities
}: PollerStatusIconProps): JSX.Element => {
  const { database, latency } = iconSeverities;

  const { classes, cx } = useStatusStyles({
    databaseSeverity: database.severity,
    latencySeverity: latency.severity
  });

  return (
    <div className={classes.container}>
      <Avatar
        className={cx(classes.database, classes.avatar)}
        title={database.label}
      >
        <StorageIcon className={classes.icon} />
      </Avatar>
      <Avatar
        className={cx(classes.latency, classes.avatar)}
        title={latency.label}
      >
        <LatencyIcon className={classes.icon} />
      </Avatar>
    </div>
  );
};

export default PollerStatusIcon;
