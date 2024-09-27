
import GroupMonitoring from './GroupMonitoring';
import { WidgetProps } from './models';

const Widget = (props: WidgetProps): JSX.Element => (
  <GroupMonitoring {...props} />
);

export default Widget;
