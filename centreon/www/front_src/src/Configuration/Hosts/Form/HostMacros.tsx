import type { InputPropsWithoutGroup } from '@centreon/ui';

import { type FormikValues, getIn } from 'formik';
import type { ReactElement } from 'react';

import Macros, {
  type InheritedMacrosConfig
} from '../../ConfigurationBase/Form/Macros';
import {
  getHostTemplateMacrosEndpoint,
  hostsBaseEndpoint
} from '../api/endpoints';
import type { NamedEntity } from '../models';
import type { TemplateRow } from './Templates';

// A host inherits the macros of its templates and of its check command.
const inheritance: InheritedMacrosConfig = {
  baseEndpoint: hostsBaseEndpoint,
  getCommandId: (values: FormikValues): number | null =>
    getIn(values, 'checkOptions.command')?.id ?? null,
  getTemplateIds: (values: FormikValues): Array<number> =>
    ((getIn(values, 'templates') ?? []) as Array<TemplateRow>)
      .filter((template): template is NamedEntity => !!template)
      .map(({ id }) => id),
  getTemplateMacrosEndpoint: getHostTemplateMacrosEndpoint,
  queryKey: 'host-form-template-macros'
};

// The host's macros, `$_HOST<NAME>$`.
const HostMacros = (props: InputPropsWithoutGroup): ReactElement => (
  <Macros {...props} inheritance={inheritance} />
);

export default HostMacros;
