import React from 'react';

import AccessibilityIcon from '@mui/icons-material/Accessibility';

import IconButton from '.';

export default { title: 'Button/Icon' };

export const normal = (): JSX.Element => (
  <IconButton size="large" title="Icon" onClick={(): undefined => undefined}>
    <AccessibilityIcon />
  </IconButton>
);
