import { useMediaQuery, useTheme } from '@mui/material';
import Grid from '@mui/material/Grid';

import { equals } from 'ramda';
import { lazy, Suspense } from 'react';

import ExportCsv from './exportToCsv';
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
  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1150))) || applyBreakPoint;

  const smallSize =
    useMediaQuery(theme.breakpoints.down(775)) ||
    equals(breakPointType, Type.small);

  return (
    <Grid className={classes.container} container>
      <Grid
        className={cx(classes.gridItem, { [classes.extraMargin]: smallSize })}
        size={7}
      >
        <Grid>
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
          <Grid>
            <Suspense fallback={<GlobalActionsSkeleton />}>
              <GlobalActions onRefresh={onRefresh} />
            </Suspense>
          </Grid>
        )}
      </Grid>
      <Grid
        className={cx({
          [classes.large]: !smallSize,
          [classes.small]: smallSize
        })}
        size={5}
        wrap="nowrap"
      >
        <VisualizationActions displayCondensed={displayCondensed} />
        <ExportCsv />
      </Grid>
    </Grid>
  );
};

export default Actions;
