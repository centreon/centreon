<<<<<<< HEAD
import { lazy, Suspense } from 'react';

import { useTheme, Grid } from '@mui/material';

import { Props } from './Refresh';
import GlobalActionsSkeleton from './GlobalActionsSkeleton';
import ResourceActionsSkeleton from './ResourceActionsSkeleton';

const ResourceActions = lazy(() => import('./Resource'));
const GlobalActions = lazy(() => import('./Refresh'));

const Actions = ({ onRefresh }: Props): JSX.Element => {
=======
import * as React from 'react';

import { useTheme, Grid } from '@material-ui/core';

import ResourceActions from './Resource';
import GlobalActions, { ActionsProps } from './Refresh';

const Actions = ({ onRefresh }: ActionsProps): JSX.Element => {
>>>>>>> centreon/dev-21.10.x
  const theme = useTheme();

  return (
    <Grid container>
      <Grid item>
<<<<<<< HEAD
        <Suspense fallback={<ResourceActionsSkeleton />}>
          <ResourceActions />
        </Suspense>
      </Grid>
      <Grid item style={{ paddingLeft: theme.spacing(3) }}>
        <Suspense fallback={<GlobalActionsSkeleton />}>
          <GlobalActions onRefresh={onRefresh} />
        </Suspense>
=======
        <ResourceActions />
      </Grid>
      <Grid item style={{ paddingLeft: theme.spacing(3) }}>
        <GlobalActions onRefresh={onRefresh} />
>>>>>>> centreon/dev-21.10.x
      </Grid>
    </Grid>
  );
};

export default Actions;
