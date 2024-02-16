import { createStore } from 'jotai';

import { Typography } from '@mui/material';

import { Module } from '@centreon/ui';

interface Props {
  store: ReturnType<typeof createStore>;
}

const Widget = ({ store }: Props): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-groupmonitoring" store={store}>
    <Typography>group monitoring</Typography>
  </Module>
);

export default Widget;
