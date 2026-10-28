import type { InputPropsWithoutGroup } from '@centreon/ui';

import { type FormikValues, getIn } from 'formik';
import type { ReactElement } from 'react';

import Macros, {
  type InheritedMacrosConfig
} from '../../ConfigurationBase/Form/Macros';
import { servicesBaseEndpoint } from '../api/endpoints';
import type { NamedEntity } from '../models';

// Placeholder until the backend exposes it, on the model of the hosts'
// `/configuration/hosts/host_templates/{id}`: one service template's macros,
// its own and those it inherits.
export const getServiceTemplateMacrosEndpoint = ({
  id
}: {
  id: number;
}): string => `/configuration/services/service_templates/${id}`;

// A service inherits the macros of its one template and of its check command.
const inheritance: InheritedMacrosConfig = {
  baseEndpoint: servicesBaseEndpoint,
  getCommandId: (values: FormikValues): number | null =>
    getIn(values, 'checkCommand')?.id ?? null,
  getTemplateIds: (values: FormikValues): Array<number> => {
    const template = getIn(values, 'template') as NamedEntity | null;

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
