import { Module } from '@centreon/ui';

import { CommonWidgetProps } from '../../models';

import { PanelOptions } from './models';

const Clock = ({
  store,
  queryClient,
  options
}: CommonWidgetProps<PanelOptions>): JSX.Element => (
  <Module queryClient={queryClient} seedName="clock" store={store}>
    <p>coucou</p>
  </Module>
);

export default Clock;
