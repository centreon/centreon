/* eslint-disable prefer-arrow-functions/prefer-arrow-functions */
/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/react-in-jsx-scope */
/* eslint-disable no-multi-assign */
/* eslint-disable func-names */

<<<<<<< HEAD
import { createRoot } from 'react-dom/client';

import Main from './Main';

const container = document.getElementById('root');
=======
import AppProvider from './Provider';
>>>>>>> centreon/dev-21.10.x

// make an IIFE function to allow "await" usage
// generate an "external" bundle to embed all needed libraries by external pages and hooks
(async function () {
  window.React = await import(/* webpackChunkName: "external" */ 'react');
  window.ReactDOM = window.ReactDom = await import(
    /* webpackChunkName: "external" */ 'react-dom'
  );
  window.PropTypes = window.PropTypes = await import(
    /* webpackChunkName: "external" */ 'prop-types'
  );
  window.ReactRouterDOM = window.ReactRouterDom = await import(
    /* webpackChunkName: "external" */ 'react-router-dom'
  );
<<<<<<< HEAD
=======
  window.ReactRedux = await import(
    /* webpackChunkName: "external" */ 'react-redux'
  );
  window.ReduxForm = await import(
    /* webpackChunkName: "external" */ 'redux-form'
  );
>>>>>>> centreon/dev-21.10.x
  window.ReactI18Next = await import(
    /* webpackChunkName: "external" */ 'react-i18next'
  );
  window.CentreonUiContext = await import(
    /* webpackChunkName: "external" */ '@centreon/ui-context'
  );
<<<<<<< HEAD
  window.Jotai = await import(/* webpackChunkName: "external" */ 'jotai');

  createRoot(container).render(<Main />);
=======

  window.ReactDOM.render(<AppProvider />, document.getElementById('root'));
>>>>>>> centreon/dev-21.10.x
})();
