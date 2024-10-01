import FederatedComponent from '../../../../../../components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { WidgetProps } from './models';

const Widget = ({ panelData, store, ...rest }: WidgetProps): JSX.Element => {
  if (!areResourcesFullfilled(panelData.resources)) {
    return <NoResources />;
  }

  return (
    <FederatedComponent
      panelData={panelData}
      path="/bam/widget"
      store={store}
      {...rest}
    />
  );
};

export default Widget;
