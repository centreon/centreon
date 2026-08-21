import { Formik } from 'formik';
import { createStore, Provider } from 'jotai';
import { useMemo } from 'react';

import { hasEditPermissionAtom, isEditingAtom } from '../../../atoms';
import WidgetWarningField from './Warning';

interface Props {
  label?: string;
}

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
        <WidgetWarningField label={label} />
      </Formik>
    </Provider>
  );
};
