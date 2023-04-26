import { makeStyles } from 'tss-react/mui';

import { SkeletonProps, useTheme, alpha } from '@mui/material';

import LoadingSkeleton from '../LoadingSkeleton';

const useStyles = makeStyles()((theme) => ({
  skeleton: {
    backgroundColor: alpha(theme.palette.grey[50], 0.4),
    borderRadius: 5
  }
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
  animate = true
}: Props): JSX.Element => {
  const theme = useTheme();
  const { classes, cx } = useStyles();

  return (
    <LoadingSkeleton
      animation={animate ? 'wave' : false}
      className={cx(classes.skeleton, className)}
      height={theme.spacing(height)}
      variant={variant}
      width={theme.spacing(width)}
    />
  );
};

export default MenuLoader;
