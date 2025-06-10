// eslint-disable-next-line import/no-relative-packages
import FederatedComponent from '../../../../front_src/src/components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { WidgetProps } from './models';

const Widget = ({ panelData, ...rest }: WidgetProps): JSX.Element => {
  if (!areResourcesFullfilled(panelData.resources)) {
    return <NoResources />;
  }

  return (
    <FederatedComponent panelData={panelData} path="/bam/widget" {...rest} />
  );
};

export default Widget;
