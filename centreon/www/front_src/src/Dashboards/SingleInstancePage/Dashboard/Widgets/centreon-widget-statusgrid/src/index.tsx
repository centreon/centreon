import { equals } from 'ramda';

import StatusGridCondensed from './StatusGridCondensed/StatusGridCondensed';
import StatusGrid from './StatusGridStandard/StatusGrid';
import { StatusGridProps } from './StatusGridStandard/models';
import { ReactElement } from 'react';

const Widget = ({ panelOptions, ...props }: StatusGridProps): ReactElement =>
  equals(panelOptions.viewMode || 'standard', 'standard') ? (
    <StatusGrid panelOptions={panelOptions} {...props} />
  ) : (
    <StatusGridCondensed panelOptions={panelOptions} {...props} />
  );

export default Widget;
