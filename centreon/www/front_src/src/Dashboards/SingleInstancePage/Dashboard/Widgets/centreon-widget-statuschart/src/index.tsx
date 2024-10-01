import StatusChart from './StatusChart';
import { StatusChartProps } from './models';

const Widget = (props: StatusChartProps): JSX.Element => (
  <StatusChart {...props} />
);

export default Widget;
