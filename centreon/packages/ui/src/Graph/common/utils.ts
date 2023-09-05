import { T, always, cond, gt, head } from 'ramda';

import { Theme } from '@mui/material';

interface GetColorFromDataAndThresholdsProps {
  data: number;
  theme: Theme;
  thresholds: Array<number>;
}

export const getColorFromDataAndTresholds = ({
  data,
  thresholds,
  theme
}: GetColorFromDataAndThresholdsProps): string =>
  cond([
    [gt(head(thresholds) as number), always(theme.palette.success.main)],
    [gt(thresholds[1]), always(theme.palette.warning.main)],
    [T, always(theme.palette.error.main)]
  ])(data);
