import React from 'react';

import Severity from './Severity';

import Snackbar from '.';

export default { title: 'Snackbar' };

export const errorSnackbar = (): JSX.Element => (
  <Snackbar
    open
    message="Something unexpected happened..."
    severity={Severity.error}
  />
);

export const successSnackbar = (): JSX.Element => (
  <Snackbar
    open
    message="Something successful happened..."
    severity={Severity.success}
  />
);

export const infoSnackbar = (): JSX.Element => (
  <Snackbar open message="Some informations..." severity={Severity.info} />
);

export const warningSnackbar = (): JSX.Element => (
  <Snackbar open message="A warning message..." severity={Severity.warning} />
);
