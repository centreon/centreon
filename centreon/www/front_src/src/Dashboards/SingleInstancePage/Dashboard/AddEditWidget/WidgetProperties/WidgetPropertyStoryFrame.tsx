import { TestQueryProvider } from '@centreon/ui';
import { type User, userAtom } from '@centreon/ui-context';

import { Formik, useFormikContext } from 'formik';
import { createStore, Provider } from 'jotai';
import { type ComponentType, type ReactNode, useMemo } from 'react';

import {
  dashboardRefreshIntervalAtom,
  hasEditPermissionAtom,
  isEditingAtom
} from '../../atoms';
import { widgetPropertiesAtom } from '../atoms';
import type { WidgetPropertyProps } from '../models';

/** Serializable setup shared by the widget property stories. */
export interface WidgetPropertyStoryProps {
  /** Edition permission; also the editing state unless isEditing is set. */
  canEdit?: boolean;
  dashboardRefreshInterval?: {
    interval: number | null;
    type: 'global' | 'manual';
  };
  /** Extra top-level form values, next to moduleName and options. */
  extraValues?: Record<string, unknown>;
  isEditing?: boolean;
  options?: Record<string, unknown>;
  user?: Partial<User>;
  widgetProperties?: Record<string, unknown>;
}

interface FrameProps extends WidgetPropertyStoryProps {
  children: ReactNode;
  /** Story-level only: a Yup schema does not cross the Node/browser boundary. */
  validationSchema?: unknown;
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
  dashboardRefreshInterval,
  extraValues = {},
  isEditing = canEdit,
  options = {},
  user,
  validationSchema,
  widgetProperties
}: FrameProps): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();

    created.set(hasEditPermissionAtom, canEdit);
    created.set(isEditingAtom, isEditing);
    if (dashboardRefreshInterval) {
      created.set(dashboardRefreshIntervalAtom, dashboardRefreshInterval);
    }
    if (user) {
      created.set(userAtom, { ...created.get(userAtom), ...user });
    }
    if (widgetProperties) {
      created.set(widgetPropertiesAtom, widgetProperties as never);
    }

    return created;
  }, [canEdit, dashboardRefreshInterval, isEditing, user, widgetProperties]);

  return (
    <TestQueryProvider>
      <Provider store={store}>
        <Formik
          initialValues={{ ...extraValues, moduleName: 'widget', options }}
          onSubmit={() => undefined}
          validationSchema={validationSchema}
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

export interface WidgetPropertyStoryArgs extends WidgetPropertyStoryProps {
  /** Props of the input under test, merged over the story defaults. */
  inputProps?: Partial<WidgetPropertyProps>;
}

/** Builds the story of a widget property input. */
export const createWidgetPropertyStory =
  (
    Input: ComponentType<WidgetPropertyProps>,
    defaults: Partial<WidgetPropertyProps> = {},
    validationSchema?: unknown
  ) =>
  ({ inputProps, ...frameProps }: WidgetPropertyStoryArgs): JSX.Element => (
    <WidgetPropertyStoryFrame
      {...frameProps}
      validationSchema={validationSchema}
    >
      <Input {...widgetPropertyProps} {...defaults} {...inputProps} />
    </WidgetPropertyStoryFrame>
  );
