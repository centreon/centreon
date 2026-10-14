import { TestQueryProvider } from '@centreon/ui';
import { type User, userAtom } from '@centreon/ui-context';

import { Formik, useFormikContext } from 'formik';
import { createStore, Provider } from 'jotai';
import { type ReactNode, useMemo } from 'react';

import { hasEditPermissionAtom, isEditingAtom } from '../../atoms';
import { widgetPropertiesAtom } from '../atoms';

/** Serializable setup shared by the widget property stories. */
export interface WidgetPropertyStoryProps {
  canEdit?: boolean;
  /** Extra top-level form values, next to moduleName and options. */
  extraValues?: Record<string, unknown>;
  options?: Record<string, unknown>;
  user?: Partial<User>;
  widgetProperties?: Record<string, unknown>;
}

/** Props required by WidgetPropertyProps that the inputs under test ignore. */
export const widgetPropertyProps = {
  forceSingleAutocompleteConditions: { conditions: [], resourceType: '' },
  isInGroup: false,
  isSingleAutocomplete: false,
  label: '',
  propertyName: 'test',
  type: ''
};

// Records the Formik values so specs assert what the input writes.
const FormValues = (): JSX.Element => {
  const { values } = useFormikContext();

  return (
    <form hidden>
      <input
        data-testid="form-values"
        readOnly
        value={JSON.stringify(values)}
      />
    </form>
  );
};

/**
 * Store and form of the widget edition panel, as every Cypress spec of
 * WidgetProperties built them. Not a story itself (no .story.tsx suffix).
 */
export const WidgetPropertyStoryFrame = ({
  canEdit = true,
  children,
  extraValues = {},
  options = {},
  user,
  widgetProperties
}: WidgetPropertyStoryProps & { children: ReactNode }): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();

    created.set(hasEditPermissionAtom, canEdit);
    created.set(isEditingAtom, canEdit);
    if (user) {
      created.set(userAtom, { ...created.get(userAtom), ...user });
    }
    if (widgetProperties) {
      created.set(widgetPropertiesAtom, widgetProperties as never);
    }

    return created;
  }, [canEdit, user, widgetProperties]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <Formik
          initialValues={{ ...extraValues, moduleName: 'widget', options }}
          onSubmit={() => undefined}
        >
          <>
            {children}
            <FormValues />
          </>
        </Formik>
      </Provider>
    </TestQueryProvider>
  );
};
