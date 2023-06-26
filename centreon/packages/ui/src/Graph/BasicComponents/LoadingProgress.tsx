import { makeStyles } from 'tss-react/mui';

import { CircularProgress, alpha } from '@mui/material';

interface StyleProps {
  height: number;
  width: number;
}

const useStyles = makeStyles<StyleProps>()((theme, { height, width }) => ({
  graphLoader: {
    alignItems: 'center',
    backgroundColor: alpha(theme.palette.common.white, 0.5),
    display: 'flex',
    height,
    justifyContent: 'center',
    position: 'absolute',
    width
  }
}));

interface LoadingProgress {
  display: boolean;
  height: number;
  width: number;
}

const LoadingProgress = ({
  display,
  height,
  width
}: LoadingProgress): JSX.Element | null => {
  const { classes } = useStyles({ height, width });

  if (!display) {
    return null;
  }

  return (
    <div className={classes.graphLoader}>
      <CircularProgress />
    </div>
  );
};

export default LoadingProgress;
