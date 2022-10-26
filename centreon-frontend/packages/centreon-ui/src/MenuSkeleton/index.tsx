<<<<<<< HEAD
import { useTheme, alpha } from '@mui/material';
=======
import clsx from 'clsx';

import { SkeletonProps, useTheme, alpha } from '@mui/material';
>>>>>>> centreon-frontend/dev-22.04.x
import makeStyles from '@mui/styles/makeStyles';

import LoadingSkeleton from '../LoadingSkeleton';

const useStyles = makeStyles((theme) => ({
  skeleton: {
    backgroundColor: alpha(theme.palette.grey[50], 0.4),
<<<<<<< HEAD
    margin: theme.spacing(0.5, 2, 1, 2),
=======
    borderRadius: 5,
    margin: theme.spacing(1, 1, 1, 2),
>>>>>>> centreon-frontend/dev-22.04.x
  },
}));

interface Props {
  animate?: boolean;
<<<<<<< HEAD
  width?: number;
}

const MenuLoader = ({ width = 15, animate = true }: Props): JSX.Element => {
=======
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
>>>>>>> centreon-frontend/dev-22.04.x
  const theme = useTheme();
  const classes = useStyles();

  return (
    <LoadingSkeleton
      animation={animate ? 'wave' : false}
<<<<<<< HEAD
      className={classes.skeleton}
      height={theme.spacing(5)}
      variant="text"
=======
      className={clsx(classes.skeleton, className)}
      height={theme.spacing(height)}
      variant={variant}
>>>>>>> centreon-frontend/dev-22.04.x
      width={theme.spacing(width)}
    />
  );
};

export default MenuLoader;
