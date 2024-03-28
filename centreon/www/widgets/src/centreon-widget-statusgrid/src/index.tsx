import { Module } from '@centreon/ui';

import { StatusGridProps } from './models';
import StatusGrid from './StatusGrid';

const Widget = ({ store, ...props }: StatusGridProps): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-statusgrid" store={store}>
    <StatusGrid {...props} />
  </Module>
);

export default Widget;
