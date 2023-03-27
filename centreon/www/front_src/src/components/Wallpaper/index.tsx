import { makeStyles } from 'tss-react/mui';

import { Image, LoadingSkeleton } from '@centreon/ui';

import memoizeComponent from '../../Resources/memoizedComponent';

import useWallpaper from './useWallpaper';

const useStyles = makeStyles()({
  placeholder: {
    bottom: 0,
    height: '100vh',
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
    width: '100vw'
  }
});

const Wallpaper = (): JSX.Element => {
  const { classes } = useStyles();

  const imagePath = useWallpaper();

  return (
    <Image
      alt="wallpaper"
      className={classes.placeholder}
      fallback={<LoadingSkeleton className={classes.placeholder} />}
      imagePath={imagePath}
    />
  );
};

export default memoizeComponent({
  Component: Wallpaper,
  memoProps: []
});
