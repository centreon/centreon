import { customFetch, type ResponseError, useSnackbar } from '@centreon/ui';

import { useQueries } from '@tanstack/react-query';
import { type FormikValues, getIn, useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useEffect, useRef } from 'react';

import {
  getHostTemplateMacrosEndpoint,
  hostsBaseEndpoint
} from '../api/endpoints';
import type { NamedEntity } from '../models';
import {
  type MacroRow,
  mergeInheritedMacros,
  resolveInheritedMacros,
  templateMacrosDecoder
} from './macroRows';
import type { TemplateRow } from './Templates';

const getTemplateIds = (values: FormikValues): Array<number> =>
  ((getIn(values, 'templates') ?? []) as Array<TemplateRow>)
    .filter((template): template is NamedEntity => !!template)
    .map(({ id }) => id);

const getCommandId = (values: FormikValues): number | null =>
  getIn(values, 'checkOptions.command')?.id ?? null;

// Keeps the inherited rows in line with the templates and the check command
// picked in the form, on creation as on edition. Until the templates and the
// command change, the host's own detail says what it inherits; after, each
// template's macros are fetched.
const useInheritedMacros = (fieldName: string): void => {
  const { showErrorMessage } = useSnackbar();
  const { initialValues, setFieldValue, values } =
    useFormikContext<FormikValues>();

  const templateIds = getTemplateIds(values);
  const hasCheckCommand = getCommandId(values) !== null;
  const isAsLoaded =
    equals(templateIds, getTemplateIds(initialValues)) &&
    equals(getCommandId(values), getCommandId(initialValues));

  const templatesMacros = useQueries({
    queries: (isAsLoaded ? [] : templateIds).map((id) => ({
      queryFn: async ({ signal }): Promise<Array<MacroRow>> => {
        const response = await customFetch<Array<MacroRow>>({
          baseEndpoint: hostsBaseEndpoint,
          decoder: templateMacrosDecoder,
          endpoint: getHostTemplateMacrosEndpoint({ id }),
          signal
        });

        if (!(response as ResponseError).isError) {
          return response as Array<MacroRow>;
        }

        // A template the endpoint does not know (or a server without the
        // endpoint yet) lends nothing.
        if ((response as ResponseError).statusCode !== 404) {
          showErrorMessage((response as ResponseError).message);
        }

        return [];
      },
      queryKey: ['host-form-template-macros', id]
    }))
  });

  const isFetched = templatesMacros.every(({ isSuccess }) => isSuccess);

  const initialInherited = (
    (getIn(initialValues, fieldName) ?? []) as Array<MacroRow>
  ).filter(({ parent }) => !!parent);

  const inherited = isAsLoaded
    ? initialInherited
    : isFetched
      ? resolveInheritedMacros({
          hasCheckCommand,
          templatesMacros: templatesMacros.map(({ data }) => data ?? [])
        })
      : null;

  // The rows change only when what is inherited does: a row the user deletes
  // or renames stays so until then.
  const appliedInherited = useRef(initialInherited);

  useEffect(() => {
    if (!inherited || equals(inherited, appliedInherited.current)) {
      return;
    }

    appliedInherited.current = inherited;
    setFieldValue(
      fieldName,
      mergeInheritedMacros(
        (getIn(values, fieldName) ?? []) as Array<MacroRow>,
        inherited
      )
    );
  }, [inherited]);
};

export default useInheritedMacros;
