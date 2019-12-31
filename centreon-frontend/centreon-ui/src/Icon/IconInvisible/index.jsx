import React from 'react';
import VisibilityOff from '@material-ui/icons/VisibilityOff';
import MaterialIcon from '../MaterialIcon';

function IconInvisible(props) {
  return (
    <MaterialIcon {...props}>
      <VisibilityOff />
    </MaterialIcon>
  );
}

export default IconInvisible;
