import { equals } from 'ramda';

import { Module } from '@centreon/ui';

import { StatusGridProps } from './StatusGridStandard/models';
import StatusGrid from './StatusGridStandard/StatusGrid';
import StatusGridCondensed from './StatusGridCondensed/StatusGridCondensed';

export const StatusGridWrapper = ({
  panelOptions,
  ...props
}: Omit<StatusGridProps, 'store' | 'queryClient'>): JSX.Element =>
  equals(panelOptions.viewMode || 'standard', 'standard') ? (
    <StatusGrid panelOptions={panelOptions} {...props} />
  ) : (
    <StatusGridCondensed panelOptions={panelOptions} {...props} />
  );

const Widget = ({
  store,
  queryClient,
  ...props
}: StatusGridProps): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-statusgrid"
    store={store}
  >
    <StatusGridWrapper {...props} />
  </Module>
);

export default Widget;
