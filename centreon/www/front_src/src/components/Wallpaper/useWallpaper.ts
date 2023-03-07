import { useMemo } from 'react';

import { always, lte, cond } from 'ramda';

import { useTheme } from '@mui/material';

import centreonWallpaperXl from '../../assets/centreon-wallpaper-xl.jpg';
import centreonWallpaperLg from '../../assets/centreon-wallpaper-lg.jpg';
import centreonWallpaperSm from '../../assets/centreon-wallpaper-sm.jpg';

const useWallpaper = (): string => {
  const theme = useTheme();

  const imagePath = useMemo(
    (): string =>
      cond<Array<number>, string>([
        [lte(theme.breakpoints.values.xl), always(centreonWallpaperXl)],
        [lte(theme.breakpoints.values.lg), always(centreonWallpaperLg)],
        [lte(theme.breakpoints.values.sm), always(centreonWallpaperSm)]
      ])(window.innerWidth),
    []
  );

  return imagePath;
};

export default useWallpaper;
