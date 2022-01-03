/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';

import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import ExpandLessIcon from '@mui/icons-material/ExpandLess';

const IconToggleSubmenu = ({ rotate, onClick }) => {
  const ExpandIcon = rotate ? ExpandLessIcon : ExpandMoreIcon;

  return (
    <ExpandIcon
      style={{ color: '#FFFFFF', cursor: 'pointer' }}
      onClick={onClick}
    />
  );
};

export default IconToggleSubmenu;
