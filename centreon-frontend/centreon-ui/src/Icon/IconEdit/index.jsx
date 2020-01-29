import React from 'react';

import { styled } from '@material-ui/core/styles';
import Edit from '@material-ui/icons/Edit';

import MaterialIcon from '../MaterialIcon';

const FloatingEdit = styled(Edit)(() => ({
  color: '#0072CE',
  fontSize: 21,
  position: 'absolute',
  right: 3,
}));

function IconEdit(props) {
  return (
    <MaterialIcon {...props}>
      <FloatingEdit />
    </MaterialIcon>
  );
}

export default IconEdit;
