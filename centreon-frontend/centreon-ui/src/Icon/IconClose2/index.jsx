/* eslint-disable react/prop-types */

import React from 'react';

import Close from '@material-ui/icons/Close';
import { styled } from '@material-ui/core/styles';

import MaterialIcon from '../MaterialIcon';

const Label = styled('span')(() => ({
  color: '#009fdf',
  cursor: 'pointer',
  display: 'inline-block',
  fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
  fontSize: 12,
  fontWeight: 'bold',
  paddingLeft: 5,
  verticalAlign: 'middle',
}));

const FloatingIcon = styled(MaterialIcon)(() => ({
  '&:hover': {
    '& svg': {
      color: '#fff',
    },
    background: '#707070',
  },
}));

const GreyClose = styled(Close)(() => ({
  color: '#424242',
  cursor: 'pointer',
  height: '100%',
  margin: 'auto',
  width: '100%',
  zIndex: 9,
}));

const IconClose = ({ label, ...rest }) => (
  <FloatingIcon {...rest} aria-label="icon close">
    <GreyClose />
    {label && <Label>{label}</Label>}
  </FloatingIcon>
);

export default IconClose;
