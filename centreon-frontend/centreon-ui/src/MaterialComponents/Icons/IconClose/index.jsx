/* eslint-disable react/prop-types */

import React from 'react';
import Close from '@material-ui/icons/Close';
import styled from '@emotion/styled';
import MaterialIcon from '../MaterialIcon';

const Label = styled.span(() => ({
  color: '#009fdf',
  fontSize: 12,
  display: 'inline-block',
  verticalAlign: 'middle',
  fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
  fontWeight: 'bold',
  cursor: 'pointer',
  paddingLeft: 5,
}));

const FloatingIcon = styled(MaterialIcon)(() => ({
  content: '""',
  position: 'absolute',
  right: 0,
  top: 0,
  width: 54,
  height: 49,
  '&:hover': {
    background: '#707070',
    '& svg': {
      color: '#fff',
    },
  },
}));

const GreyClose = styled(Close)(() => ({
  color: '#424242',
  cursor: 'pointer',
  float: 'right',
  fontSize: 32,
  zIndex: 9,
  position: 'absolute',
  right: 11,
  top: 8,
}));

function IconClose({ label, ...rest }) {
  return (
    <FloatingIcon {...rest} aria-label="Close">
      <GreyClose />
      {label && <Label>{label}</Label>}
    </FloatingIcon>
  );
}

export default IconClose;
