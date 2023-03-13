import { useMemo } from 'react';

import { always, cond, T, gt } from 'ramda';

import { useTheme } from '@mui/material';

import centreonWallpaperXl from '../../assets/centreon-wallpaper-xl.jpg';
import centreonWallpaperLg from '../../assets/centreon-wallpaper-lg.jpg';
import centreonWallpaperSm from '../../assets/centreon-wallpaper-sm.jpg';

const useWallpaper = (): string => {
  const theme = useTheme();

  const imagePath = useMemo(
    (): string =>
      cond<Array<number>, string>([
        [gt(theme.breakpoints.values.sm), always(centreonWallpaperSm)],
        [gt(theme.breakpoints.values.lg), always(centreonWallpaperLg)],
        [T, always(centreonWallpaperXl)]
      ])(window.innerWidth),
    []
  );

  return imagePath;
};

export default useWallpaper;
