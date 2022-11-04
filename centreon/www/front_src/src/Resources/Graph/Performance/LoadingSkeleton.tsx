import { makeStyles } from 'tss-react/mui';

import { Skeleton } from '@mui/material';

interface Props {
  displayTitleSkeleton: boolean;
  graphHeight: number;
}

// TODO jss-to-tss-react codemod: Unable to handle style definition reliably. ArrowFunctionExpression in CSS prop.
const useSkeletonStyles = makeStyles()((theme) => ({
  loadingSkeleton: {
    display: 'grid',
    gridGap: theme.spacing(1),
    gridTemplateRows: ({ graphHeight, displayTitleSkeleton }: Props): string =>
      `${displayTitleSkeleton ? '1fr' : ''} ${graphHeight}px ${theme.spacing(
        7,
      )}`,
    height: '100%',
  },
  loadingSkeletonLine: {
    paddingBottom: theme.spacing(1),
    transform: 'none',
  },
}));

const LoadingSkeleton = ({
  graphHeight,
  displayTitleSkeleton,
}: Props): JSX.Element => {
  const { classes } = useSkeletonStyles({ displayTitleSkeleton, graphHeight });

  const skeletonLine = <Skeleton className={classes.loadingSkeletonLine} />;

  return (
    <div className={classes.loadingSkeleton}>
      {displayTitleSkeleton && skeletonLine}
      {skeletonLine}
      {skeletonLine}
    </div>
  );
};

export default LoadingSkeleton;
