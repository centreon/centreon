import { Module } from '@centreon/ui';

import { CommonWidgetProps } from '../../models';

const Clock = ({
  store,
  queryClient
}: CommonWidgetProps<object>): JSX.Element => (
  <Module queryClient={queryClient} seedName="clock" store={store}>
    <p>coucou</p>
  </Module>
);

export default Clock;
