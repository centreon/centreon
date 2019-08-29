import React from 'react';
import Attach from '@material-ui/icons/AttachFile';
import styled from '@emotion/styled';
import MaterialIcon from '../MaterialIcon';

const AttachMaterial = styled(MaterialIcon)(() => ({
  width: 80,
  height: 49,
  textAlign: 'center',
  lineHeight: '49px',
  marginRight: 15,
  marginLeft: -20,
}));

const MiniAttach = styled(Attach)(() => ({
  fontSize: 14,
  verticalAlign: 'middle',
}));

const Label = styled.span(() => ({
  fontSize: 12,
  fontFamily: '"Roboto", "Helvetica", "Arial", sans-serif',
  marginLeft: 3,
}));

function IconAttach(props) {
  return (
    <AttachMaterial {...props}>
      <MiniAttach />
      <Label>ICON</Label>
    </AttachMaterial>
  );
}

export default IconAttach;
