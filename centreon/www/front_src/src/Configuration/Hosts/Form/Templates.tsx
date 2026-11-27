import type { InputPropsWithoutGroup } from '@centreon/ui';

import type { ReactElement } from 'react';

import OrderedTemplates, {
  type OrderedTemplatesConfig
} from '../../ResourceForm/OrderedTemplates';
import {
  getFormHostTemplatesEndpoint,
  hostsBaseEndpoint
} from '../api/endpoints';
import { getHostTemplateConfigurationUrl } from '../utils';

const config: OrderedTemplatesConfig = {
  baseEndpoint: hostsBaseEndpoint,
  getEndpoint: getFormHostTemplatesEndpoint,
  // A new tab, so the form being filled is not lost.
  onEdit: (id) => {
    window.open(
      getHostTemplateConfigurationUrl(id),
      '_blank',
      'noopener,noreferrer'
    );
  },
  queryKey: 'host-form-templates'
};

const Templates = (props: InputPropsWithoutGroup): ReactElement => (
  <OrderedTemplates {...props} config={config} />
);

export default Templates;
