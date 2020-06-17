import React from 'react';

import AccessibilityIcon from '@material-ui/icons/Accessibility';

import IconButton from '.';

export default { title: 'Button/Icon' };

export const normal = (): JSX.Element => (
  <IconButton title="Icon" onClick={() => undefined}>
    <AccessibilityIcon />
  </IconButton>
);
