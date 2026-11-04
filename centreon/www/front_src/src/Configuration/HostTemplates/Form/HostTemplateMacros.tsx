import type { InputPropsWithoutGroup } from '@centreon/ui';

import { type FormikValues, getIn } from 'formik';
import type { ReactElement } from 'react';

import Macros, {
  type InheritedMacrosConfig
} from '../../ConfigurationBase/Form/Macros';
import {
  getHostTemplateMacrosEndpoint,
  hostTemplatesBaseEndpoint
} from '../api/endpoints';
import type { NamedEntity } from '../models';

// A template inherits the macros of its parent templates and of its check
// command.
const inheritance: InheritedMacrosConfig = {
  baseEndpoint: hostTemplatesBaseEndpoint,
  getCommandId: (values: FormikValues): number | null =>
    getIn(values, 'checkOptions.command')?.id ?? null,
  getTemplateIds: (values: FormikValues): Array<number> =>
    ((getIn(values, 'templates') ?? []) as Array<NamedEntity | null>)
      .filter((template): template is NamedEntity => !!template)
      .map(({ id }) => id),
  getTemplateMacrosEndpoint: getHostTemplateMacrosEndpoint,
  queryKey: 'host-template-form-template-macros'
};

// The template's macros, `$_HOST<NAME>$`.
const HostTemplateMacros = (props: InputPropsWithoutGroup): ReactElement => (
  <Macros {...props} inheritance={inheritance} />
);

export default HostTemplateMacros;
