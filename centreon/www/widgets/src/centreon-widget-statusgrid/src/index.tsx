import { equals } from 'ramda';

import { Module } from '@centreon/ui';

import { StatusGridProps } from './models';
import StatusGrid from './StatusGrid';

const Widget = ({ store, ...props }: StatusGridProps): JSX.Element => (
  <Module maxSnackbars={1} seedName="widget-statusgrid" store={store}>
    {equals(props.panelOptions.viewMode, 'standard') ? (
      <StatusGrid {...props} />
    ) : (
      <div>coucou</div>
    )}
  </Module>
);

export default Widget;
