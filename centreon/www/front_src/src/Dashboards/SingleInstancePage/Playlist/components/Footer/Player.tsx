import { Box } from '@mui/material';
import SkipPreviousIcon from '@mui/icons-material/SkipPrevious';
import SkipNextIcon from '@mui/icons-material/SkipNext';
import PlayCircleIcon from '@mui/icons-material/PlayCircle';
import PauseCircleIcon from '@mui/icons-material/PauseCircle';

import { IconButton } from '@centreon/ui/components';

import { Dashboard } from '../../../../components/DashboardPlaylists/models';

import { usePlayerActions } from './usePlayerActions';
import { usePlayerStyles } from './Footer.styles';

interface Props {
  dashboards: Array<Dashboard>;
}

const Player = ({ dashboards }: Props): JSX.Element => {
  const { classes } = usePlayerStyles();
  const { isRotatingDashboards, next, previous, playPause } = usePlayerActions({
    dashboards
  });

  const PlayPauseIcon = isRotatingDashboards ? PauseCircleIcon : PlayCircleIcon;

  return (
    <Box className={classes.player}>
      <IconButton
        disableRipple
        icon={<SkipPreviousIcon className={classes.icon} />}
        size="small"
        onClick={previous}
      />
      <IconButton
        disableRipple
        icon={<PlayPauseIcon className={classes.icon} data-size="large" />}
        size="small"
        onClick={playPause}
      />
      <IconButton
        disableRipple
        icon={<SkipNextIcon className={classes.icon} />}
        size="small"
        onClick={next}
      />
    </Box>
  );
};

export default Player;
