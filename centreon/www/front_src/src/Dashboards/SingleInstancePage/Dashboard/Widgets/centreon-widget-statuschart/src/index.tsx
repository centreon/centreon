import { ReactElement } from 'react';

import { StatusChartProps } from './models';
import StatusChart from './StatusChart';

const Widget = (props: StatusChartProps): ReactElement => (
  <StatusChart {...props} />
);

export default Widget;
