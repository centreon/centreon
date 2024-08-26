import { Module } from '@centreon/ui';

import Webpage from './WebPage';
import type { WebPageProps } from './models';

const Widget = ({
  store,
  queryClient,
  ...props
}: WebPageProps): JSX.Element => (
  <Module
    maxSnackbars={1}
    queryClient={queryClient}
    seedName="widget-webpage"
    store={store}
  >
    <Webpage {...props} />
  </Module>
);

export default Widget;
