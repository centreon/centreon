import { ReactElement } from 'react';

import { Skeleton as MuiSkeleton } from '@mui/material';

import { useStyles } from './ListItem.styles';

type TextSkeletonProps = {
  secondaryText?: boolean;
};

export const TextSkeleton = ({
  secondaryText
}: TextSkeletonProps): ReactElement => {
  const { classes } = useStyles();

  return (
    <span className={classes.textSkeleton}>
      <MuiSkeleton animation="wave" variant="text" />
      {!!secondaryText && <MuiSkeleton animation="wave" variant="text" />}
    </span>
  );
};
