import { Module } from '@centreon/ui';

import GroupMonitoring from './GroupMonitoring';
import { WidgetProps } from './models';

const Widget = ({ store, queryClient, ...rest }: WidgetProps): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-groupmonitoring"
    store={store}
  >
    <GroupMonitoring {...rest} />
  </Module>
);

export default Widget;
