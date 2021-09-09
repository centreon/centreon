/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */

import React from 'react';

import ExpandMoreIcon from '@material-ui/icons/ExpandMore';
import ExpandLessIcon from '@material-ui/icons/ExpandLess';

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
