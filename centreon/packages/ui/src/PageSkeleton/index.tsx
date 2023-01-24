import { makeStyles } from 'tss-react/mui';

import { useTheme } from '@mui/material';

import LoadingSkeleton from '../LoadingSkeleton';

import BaseRectSkeleton, { useSkeletonStyles } from './BaseSkeleton';
import ContentSkeleton from './ContentSkeleton';

const headerHeight = 8;
const footerHeight = 3.8;

const useStyles = makeStyles()((theme) => ({
  breadcrumbSkeleton: {
    margin: theme.spacing(0.5, 2),
    width: theme.spacing(30)
  },
  headerContentFooterContainer: {
    alignContent: 'space-between',
    display: 'grid',
    gridTemplateRows: `auto ${theme.spacing(footerHeight)}`,
    height: '100%',
    rowGap: theme.spacing(1)
  },
  menuContentContainer: {
    display: 'grid',
    gridTemplateColumns: `${theme.spacing(5.5)} 1fr`,
    height: '100%'
  },
  skeletonContainer: {
    height: '100%',
    width: '100%'
  }
}));

export interface PageSkeletonProps {
  animate?: boolean;
  displayHeaderAndNavigation?: boolean;
}

const PageSkeleton = ({
  displayHeaderAndNavigation = false,
  animate = true
}: PageSkeletonProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const { classes: skeletonClasses } = useSkeletonStyles();
  const theme = useTheme();

  return (
    <div className={classes.skeletonContainer}>
      <div
        className={cx({
          [classes.menuContentContainer]: displayHeaderAndNavigation
        })}
      >
        <BaseRectSkeleton
          animate={animate}
          height="100%"
          width={`calc(100% - ${theme.spacing(0.5)})`}
        />
        <div className={classes.headerContentFooterContainer}>
          <div>
            {displayHeaderAndNavigation && (
              <BaseRectSkeleton
                animate={animate}
                height={theme.spacing(headerHeight)}
              />
            )}
            <LoadingSkeleton
              animation={animate ? 'wave' : false}
              className={cx(
                classes.breadcrumbSkeleton,
                skeletonClasses.skeletonLayout
              )}
              height={theme.spacing(2.5)}
              variant="text"
            />
            <ContentSkeleton animate={animate} />
          </div>
          {displayHeaderAndNavigation && (
            <BaseRectSkeleton
              animate={animate}
              height={theme.spacing(footerHeight)}
            />
          )}
        </div>
      </div>
    </div>
  );
};

export default PageSkeleton;
