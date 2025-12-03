import { ReactElement } from 'react';
import StatusChart from './StatusChart';
import { StatusChartProps } from './models';

const Widget = (props: StatusChartProps): ReactElement => (
  <StatusChart {...props} />
);

export default Widget;
