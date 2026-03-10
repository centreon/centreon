import { equals } from 'ramda';

import StatusGridCondensed from './StatusGridCondensed/StatusGridCondensed';
import { StatusGridProps } from './StatusGridStandard/models';
import StatusGrid from './StatusGridStandard/StatusGrid';

const Widget = ({ panelOptions, ...props }: StatusGridProps): JSX.Element =>
  equals(panelOptions.viewMode || 'standard', 'standard') ? (
    <StatusGrid panelOptions={panelOptions} {...props} />
  ) : (
    <StatusGridCondensed panelOptions={panelOptions} {...props} />
  );

export default Widget;
