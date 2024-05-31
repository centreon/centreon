import { Module } from '@centreon/ui';

import { WidgetProps } from './models';
import GroupMonitoring from './GroupMonitoring';

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
