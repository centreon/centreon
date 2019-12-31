import React from 'react';

import Snackbar from '.';

export default { title: 'Snackbar/Error' };

export const normal = () => (
  <Snackbar open message="Something unexpected happened..." />
);
