import * as React from 'react';

import { useTheme, makeStyles } from '@material-ui/core';
import { Skeleton, SkeletonProps } from '@material-ui/lab';

const headerHeight = 3.8;

const useStyles = makeStyles((theme) => ({
  nextContent: {
    marginTop: theme.spacing(1.5),
  },
}));

interface Props {
  animate?: boolean;
}

const BaseSkeleton = ({
  animate,
  ...props
}: Pick<Props, 'animate'> & SkeletonProps): JSX.Element => (
  <Skeleton animation={animate ? 'wave' : false} {...props} />
);

export const SliderSkeleton = ({ animate = true }: Props): JSX.Element => {
  const theme = useTheme();

  return (
    <BaseSkeleton
      variant="rect"
      width="100%"
      height={theme.spacing(50)}
      animate={animate}
    />
  );
};

export const HeaderSkeleton = ({ animate = true }: Props): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();

  return (
    <>
      <BaseSkeleton
        variant="rect"
        height={theme.spacing(headerHeight)}
        width={theme.spacing(10)}
        animate={animate}
      />
      <BaseSkeleton
        variant="rect"
        height={theme.spacing(headerHeight)}
        width={theme.spacing(20)}
        className={classes.nextContent}
        animate={animate}
      />
    </>
  );
};

export const ContentSkeleton = ({ animate = true }: Props): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();

  return (
    <>
      <BaseSkeleton
        variant="text"
        width={theme.spacing(20)}
        animate={animate}
      />
      <BaseSkeleton
        variant="text"
        width={theme.spacing(15)}
        className={classes.nextContent}
        animate={animate}
      />
      <BaseSkeleton
        variant="text"
        width={theme.spacing(25)}
        className={classes.nextContent}
        animate={animate}
      />
    </>
  );
};

export const ReleaseNoteSkeleton = ({ animate = true }: Props): JSX.Element => {
  const theme = useTheme();
  const classes = useStyles();

  return (
    <>
      <BaseSkeleton
        variant="text"
        width={theme.spacing(15)}
        animate={animate}
      />
      <BaseSkeleton
        variant="text"
        width={theme.spacing(25)}
        className={classes.nextContent}
        animate={animate}
      />
    </>
  );
};
