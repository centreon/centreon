import { Formik } from 'formik';
import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import { hasEditPermissionAtom, isEditingAtom } from '../../../atoms';
import WidgetWarningField from './Warning';

interface Props {
  label?: string;
}

// Required by WidgetPropertyProps but unused by the warning field.
const widgetPropertyProps = {
  forceSingleAutocompleteConditions: { conditions: [], resourceType: '' },
  isInGroup: false,
  isSingleAutocomplete: false,
  propertyName: 'warning',
  type: 'warning'
};

export const WithLabel = ({ label = 'Text' }: Props): JSX.Element => {
  const store = useMemo(() => {
    const created = createStore();

    created.set(hasEditPermissionAtom, true);
    created.set(isEditingAtom, true);

    return created;
  }, []);

  return (
    <Provider store={store}>
      <Formik
        initialValues={{ moduleName: 'widget', options: {} }}
        onSubmit={() => undefined}
      >
        <WidgetWarningField {...widgetPropertyProps} label={label} />
      </Formik>
    </Provider>
  );
};
