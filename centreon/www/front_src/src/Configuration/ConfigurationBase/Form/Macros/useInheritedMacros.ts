import { customFetch, type ResponseError, useSnackbar } from '@centreon/ui';

import { useQueries } from '@tanstack/react-query';
import { type FormikValues, getIn, useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useEffect, useRef } from 'react';

import {
  type MacroRow,
  mergeInheritedMacros,
  resolveInheritedMacros,
  templateMacrosDecoder
} from './macroRows';
import type { InheritedMacrosConfig } from './models';

// Keeps the inherited rows in line with the templates and the command picked
// in the form, on creation as on edition. Until the templates and the command
// change, the resource's own detail says what it inherits; after, each
// template's macros are fetched.
const useInheritedMacros = ({
  config: {
    baseEndpoint,
    getCommandId,
    getTemplateIds,
    getTemplateMacrosEndpoint,
    queryKey
  },
  fieldName
}: {
  config: InheritedMacrosConfig;
  fieldName: string;
}): void => {
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
          baseEndpoint,
          decoder: templateMacrosDecoder,
          endpoint: getTemplateMacrosEndpoint({ id }),
          signal
        });

        if (!(response as ResponseError).isError) {
          return response as Array<MacroRow>;
        }

        // A failure leaves the rows as they are. A 404 does so silently: it is
        // a template the endpoint does not know, or a server without the
        // endpoint yet, not a template without macros.
        if ((response as ResponseError).statusCode !== 404) {
          showErrorMessage((response as ResponseError).message);
        }

        throw new Error((response as ResponseError).message);
      },
      queryKey: [queryKey, id],
      retry: false
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
          ownCommandMacros: equals(
            getCommandId(values),
            getCommandId(initialValues)
          )
            ? initialInherited.filter(({ parent }) => parent === 'command')
            : [],
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
