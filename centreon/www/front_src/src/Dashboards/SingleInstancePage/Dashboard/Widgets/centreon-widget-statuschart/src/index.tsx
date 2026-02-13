import { StatusChartProps } from './models';
import StatusChart from './StatusChart';

const Widget = (props: StatusChartProps): JSX.Element => (
  <StatusChart {...props} />
);

export default Widget;
