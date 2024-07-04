import { always, cond, equals } from 'ramda';

import { Box, useTheme } from '@mui/material';
import PersonIcon from '@mui/icons-material/Person';
import DnsIcon from '@mui/icons-material/Dns';
import GrainIcon from '@mui/icons-material/Grain';

import { DowntimeIcon } from '@centreon/ui';

import { getColor } from './utils';
import { useTileStyles } from './StatusGrid.styles';

interface Props {
  isAcknowledged?: boolean;
  isCompact: boolean;
  isInDowntime?: boolean;
  type: string;
}

const getStateIcon = ({
  isAcknowledged,
  isInDowntime
}: Pick<Props, 'isInDowntime' | 'isAcknowledged'>): JSX.Element | null => {
  if (isAcknowledged) {
    return <PersonIcon />;
  }

  if (isInDowntime) {
    return <DowntimeIcon />;
  }

  return null;
};

const getResourceTypeIcon = cond([
  [equals('host'), always(<DnsIcon />)],
  [equals('service'), always(<GrainIcon />)]
]);

const State = ({
  isCompact,
  isAcknowledged,
  isInDowntime,
  type
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes } = useTileStyles();

  return (
    <Box
      className={classes.statusTile}
      data-isAcknowledged={isAcknowledged}
      data-isInDowntime={isInDowntime}
      data-mode={isCompact ? 'compact' : 'normal'}
      sx={{
        backgroundColor: getColor({
          is_acknowledged: isAcknowledged,
          is_in_downtime: isInDowntime,
          severityCode: undefined,
          theme
        })
      }}
    >
      {!isCompact && (
        <div className={classes.stateContent}>
          <div className={classes.stateIcon}>
            {getStateIcon({ isAcknowledged, isInDowntime })}
          </div>
          <div className={classes.resourceTypeIcon}>
            {getResourceTypeIcon(type)}
          </div>
        </div>
      )}
    </Box>
  );
};

export default State;
