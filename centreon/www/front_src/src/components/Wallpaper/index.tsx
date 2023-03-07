import { makeStyles } from 'tss-react/mui';

import memoizeComponent from '../../Resources/memoizedComponent';

import { Image, ImageVariant, LoadingSkeleton } from '@centreon/ui';

import useWallpaper from './useWallpaper';

const useStyles = makeStyles()({
  placeholder: {
    bottom: 0,
    left: 0,
    position: 'absolute',
    right: 0,
    top: 0,
    height: '100vh',
    width: '100vw',
  }
});

const Wallpaper = (): JSX.Element => {
  const { classes } = useStyles();

  const imagePath = useWallpaper();

  return (
    <Image imagePath={imagePath} alt="wallpaper" className={classes.placeholder} fallback={<LoadingSkeleton className={classes.placeholder} />} />
  );
};

export default memoizeComponent({
  Component: Wallpaper,
  memoProps: []
});
