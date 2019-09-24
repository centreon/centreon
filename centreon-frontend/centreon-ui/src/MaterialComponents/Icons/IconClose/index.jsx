/* eslint-disable react/prop-types */

import React from 'react';
import Close from '@material-ui/icons/Close';
import { styled } from '@material-ui/core/styles';
import MaterialIcon from '../MaterialIcon';

const Label = styled('span')(() => ({
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
  '&:hover': {
    background: '#707070',
    '& svg': {
      color: '#fff',
    },
  },
}));

const GreyClose = styled(Close)(() => ({
  margin: 'auto',
  height: '100%',
  width: '100%',
  color: '#424242',
  cursor: 'pointer',
  zIndex: 9,
}));

function IconClose({ label, ...rest }) {
  return (
    <FloatingIcon {...rest} aria-label="icon close">
      <GreyClose />
      {label && <Label>{label}</Label>}
    </FloatingIcon>
  );
}

export default IconClose;
