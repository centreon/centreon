import { ReactElement } from 'react';
import GroupMonitoring from './GroupMonitoring';
import { WidgetProps } from './models';

const Widget = (props: WidgetProps): ReactElement => (
  <GroupMonitoring {...props} />
);

export default Widget;
