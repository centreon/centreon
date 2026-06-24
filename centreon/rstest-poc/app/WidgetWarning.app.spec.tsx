import { describe, expect, it, rstest } from '@rstest/core';
import { Formik } from 'formik';
import { createStore, Provider } from 'jotai';

import WidgetWarningField from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/AddEditWidget/WidgetProperties/Inputs/Warning';
import {
  hasEditPermissionAtom,
  isEditingAtom
} from '../../www/front_src/src/Dashboards/SingleInstancePage/Dashboard/atoms';
import { renderApp, screen } from './render';

/** Phase 0b port: a Formik + Jotai-state widget input. */
describe('WidgetWarningField (Rstest app POC)', () => {
  it('displays the warning text field', () => {
    const store = createStore();
    store.set(hasEditPermissionAtom, true);
    store.set(isEditingAtom, true);

    const label = 'Warning message!';

    renderApp(
      <Provider store={store}>
        <Formik
          initialValues={{ moduleName: 'widget', options: {} }}
          onSubmit={rstest.fn()}
        >
          <WidgetWarningField label={label} />
        </Formik>
      </Provider>
    );

    expect(screen.getByText(label)).toBeVisible();
  });
});
