import { lazy, Suspense } from 'react';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { Grid, useMediaQuery, useTheme } from '@mui/material';

import { featureFlagsDerivedAtom } from '@centreon/ui-context';

import GlobalActionsSkeleton from './GlobalActionsSkeleton';
import { Type } from './model';
import { Props } from './Refresh';
import useMediaQueryListing from './Resource/useMediaQueryListing';
import ResourceActionsSkeleton from './ResourceActionsSkeleton';
import VisualizationActions from './Visualization';
import { useStyles } from './Visualization/Visualization.styles';

const WrapperResourceActions = lazy(() => import('./WrapperResourceActions'));
const GlobalActions = lazy(() => import('./Refresh'));

const Actions = ({ onRefresh }: Props): JSX.Element => {
  const { classes, cx } = useStyles();
  const theme = useTheme();

  const { applyBreakPoint, breakPointType } = useMediaQueryListing();
  const featureFlags = useAtomValue(featureFlagsDerivedAtom);
  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1150))) || applyBreakPoint;

  const smallSize =
    useMediaQuery(theme.breakpoints.down(775)) ||
    equals(breakPointType, Type.small);

  return (
    <Grid container className={classes.container}>
      <Grid
        item
        className={cx(classes.gridItem, { [classes.extraMargin]: smallSize })}
      >
        <Grid item>
          <Suspense fallback={<ResourceActionsSkeleton />}>
            <WrapperResourceActions
              displayCondensed={displayCondensed}
              renderMoreSecondaryActions={({ close }) => (
                <GlobalActions
                  displayAsIcons={false}
                  displayAsList={{ close, display: smallSize }}
                  onRefresh={onRefresh}
                />
              )}
            />
          </Suspense>
        </Grid>
        {!smallSize && (
          <Grid item>
            <Suspense fallback={<GlobalActionsSkeleton />}>
              <GlobalActions onRefresh={onRefresh} />
            </Suspense>
          </Grid>
        )}
      </Grid>
      <Grid
        item
        className={cx({
          [classes.large]: !smallSize
        })}
      >
        {featureFlags?.resourceStatusTreeView && (
          <VisualizationActions displayCondensed={displayCondensed} />
        )}
      </Grid>
    </Grid>
  );
};

export default Actions;
