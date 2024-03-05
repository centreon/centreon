import { equals } from 'ramda';

import { Module } from '@centreon/ui';

import { StatusGridProps } from './StatusGridStandard/models';
import StatusGrid from './StatusGridStandard/StatusGrid';
import StatusGridCondensed from './StatusGridCondensed/StatusGridCondensed';

const Widget = ({ store, ...props }: StatusGridProps): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-statusgrid" store={store}>
    {equals(props.panelOptions.viewMode, 'standard') ? (
      <StatusGrid {...props} />
    ) : (
      <StatusGridCondensed {...props} />
    )}
  </Module>
);

export default Widget;
