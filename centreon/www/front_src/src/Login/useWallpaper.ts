import { useMemo } from 'react';

import { T, always, cond, gt } from 'ramda';

import { useTheme } from '@mui/material';

import centreonWallpaperLg from '../assets/centreon-wallpaper-lg.jpg';
import centreonWallpaperSm from '../assets/centreon-wallpaper-sm.jpg';
import centreonWallpaperXl from '../assets/centreon-wallpaper-xl.jpg';

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
