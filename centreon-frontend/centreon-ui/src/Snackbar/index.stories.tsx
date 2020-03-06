import React from 'react';

import Snackbar from '.';
import Severity from './Severity';

export default { title: 'Snackbar' };

export const errorSnackbar = (): JSX.Element => (
  <Snackbar
    open
    message="Something unexpected happened..."
    severity={Severity.error}
  />
);

export const normal = (): JSX.Element => (
  <Snackbar open message="Something successful happened..." />
);

export const infoSnackbar = (): JSX.Element => (
  <Snackbar open message="Some informations..." severity={Severity.info} />
);

export const warningSnackbar = (): JSX.Element => (
  <Snackbar open message="A warning message..." severity={Severity.warning} />
);
