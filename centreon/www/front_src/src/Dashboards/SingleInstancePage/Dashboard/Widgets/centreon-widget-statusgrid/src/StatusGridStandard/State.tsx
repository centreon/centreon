import { Box, useTheme } from '@mui/material';

import { AcknowledgementIcon, DowntimeIcon } from '@centreon/ui';

import { useTileStyles } from './StatusGrid.styles';
import { getColor } from './utils';

interface Props {
  isAcknowledged?: boolean;
  isCompact: boolean;
  isInDowntime?: boolean;
}

const getStateIcon = ({
  isAcknowledged,
  isInDowntime
}: Pick<Props, 'isInDowntime' | 'isAcknowledged'>): JSX.Element | null => {
  if (isAcknowledged) {
    return <AcknowledgementIcon />;
  }

  if (isInDowntime) {
    return <DowntimeIcon />;
  }

  return null;
};

const State = ({
  isCompact,
  isAcknowledged,
  isInDowntime
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
        </div>
      )}
    </Box>
  );
};

export default State;
