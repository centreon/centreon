import React from 'react';
import DoneIcon from '@material-ui/icons/Done';
import MaterialIcon from '../MaterialIcon';

function IconDone(props) {
  return (
    <MaterialIcon {...props}>
      <DoneIcon />
    </MaterialIcon>
  );
}

export default IconDone;
