import { Box, useTheme } from '@mui/material';

import { AcknowledgementIcon, DowntimeIcon, FlappingIcon } from '@centreon/ui';

import { useTileStyles } from './StatusGrid.styles';
import { getColor } from './utils';

interface Props {
  isAcknowledged?: boolean;
  isCompact: boolean;
  isInDowntime?: boolean;
  isInFlapping?: boolean;
}

const getStateIcon = ({
  isAcknowledged,
  isInDowntime,
  isInFlapping
}: Pick<
  Props,
  'isInDowntime' | 'isAcknowledged' | 'isInFlapping'
>): JSX.Element | null => {
  if (isInDowntime) {
    return <DowntimeIcon />;
  }

  if (isAcknowledged) {
    return <AcknowledgementIcon />;
  }

  if (isInFlapping) {
    return <FlappingIcon />;
  }

  return null;
};

const State = ({
  isCompact,
  isAcknowledged,
  isInDowntime,
  isInFlapping
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
          is_in_flapping: isInFlapping,
          severityCode: undefined,
          theme
        })
      }}
    >
      {!isCompact && (
        <div className={classes.stateContent}>
          <div className={classes.stateIcon}>
            {getStateIcon({ isAcknowledged, isInDowntime, isInFlapping })}
          </div>
        </div>
      )}
    </Box>
  );
};

export default State;
