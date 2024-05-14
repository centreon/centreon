import { Module } from '@centreon/ui';

// eslint-disable-next-line import/no-relative-packages
import FederatedComponent from '../../../../front_src/src/components/FederatedComponents';
import NoResources from '../../NoResources';
import { areResourcesFullfilled } from '../../utils';

import { WidgetProps } from './models';

const Widget = ({ panelData, store, ...rest }: WidgetProps): JSX.Element => {
  if (!areResourcesFullfilled(panelData.resources)) {
    return <NoResources />;
  }

  return (
    <Module seedName="centreon-widget-batree" store={store}>
      <FederatedComponent
        panelData={panelData}
        path="/bam/widget"
        store={store}
        {...rest}
      />
    </Module>
  );
};

export default Widget;
