import clsx from 'clsx';

import { SkeletonProps, useTheme, alpha } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';

import LoadingSkeleton from '../LoadingSkeleton';

const useStyles = makeStyles((theme) => ({
  skeleton: {
    backgroundColor: alpha(theme.palette.grey[50], 0.4),
    borderRadius: 5,
    margin: theme.spacing(1, 1, 1, 2),
  },
}));

interface Props {
  animate?: boolean;
  className?: string;
  height?: number;
  variant?: SkeletonProps['variant'];
  width?: number;
}

const MenuLoader = ({
  width = 15,
  height = 5,
  className,
  variant = 'rectangular',
  animate = true,
}: Props): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();

  return (
    <LoadingSkeleton
      animation={animate ? 'wave' : false}
      className={clsx(classes.skeleton, className)}
      height={theme.spacing(height)}
      variant={variant}
      width={theme.spacing(width)}
    />
  );
};

export default MenuLoader;
