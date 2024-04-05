// eslint-disable-next-line import/no-relative-packages
import FederatedComponent from '../../../../front_src/src/components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { WidgetProps } from './models';

const Widget = ({ panelData }: WidgetProps): JSX.Element => {
  if (!areResourcesFullfilled(panelData.resources)) {
    return <NoResources />;
  }

  return <FederatedComponent path="" />;
};

export default Widget;
