import type { InputPropsWithoutGroup } from '@centreon/ui';

import { type FormikValues, getIn } from 'formik';
import type { ReactElement } from 'react';

import Macros, {
  type InheritedMacrosConfig
} from '../../ConfigurationBase/Form/Macros';
import {
  getServiceTemplateMacrosEndpoint,
  servicesBaseEndpoint
} from '../api/endpoints';
import type { NamedEntity } from '../models';
import { serviceTemplateKey } from './inheritance';

// A service inherits the macros of its one template and of its check command.
const inheritance: InheritedMacrosConfig = {
  baseEndpoint: servicesBaseEndpoint,
  getCommandId: (values: FormikValues): number | null =>
    getIn(values, 'checkOptions.command')?.id ?? null,
  getTemplateIds: (values: FormikValues): Array<number> => {
    const template = getIn(values, serviceTemplateKey) as NamedEntity | null;

    return template ? [template.id] : [];
  },
  getTemplateMacrosEndpoint: getServiceTemplateMacrosEndpoint,
  queryKey: 'service-form-template-macros'
};

// The service's macros, `$_SERVICE<NAME>$`.
const ServiceMacros = (props: InputPropsWithoutGroup): ReactElement => (
  <Macros {...props} inheritance={inheritance} />
);

export default ServiceMacros;
