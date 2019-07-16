import React from 'react';
import PowerSettings from '@material-ui/icons/PowerSettingsNew';
import styled from '@emotion/styled';
import MaterialIcon from '../MaterialIcon';

const RoundedPowerSettings = styled(PowerSettings)(() => ({
  color: '#fff',
  cursor: 'pointer',
  backgroundColor: '#707070',
  borderRadius: '50%',
  fontSize: 15,
  padding: 3,
}));

const DisablingMaterialIcon = styled(MaterialIcon)(() => ({
  display: 'inline-block',
  verticalAlign: 'middle',
  height: 23,
  width: 23,
  position: 'relative',
  '&::after': {
    content: "''",
    position: 'absolute',
    width: 2,
    height: 30,
    background: '#7f7f7f',
    transform: 'rotate(140deg)',
    left: 9,
    top: -2,
    zIndex: 1,
  },
  '&::before': {
    content: "''",
    position: 'absolute',
    width: 4,
    height: 30,
    background: '#fff',
    transform: 'rotate(140deg)',
    left: 9,
    top: -3,
    zIndex: 1,
  },
}));

function IconPowerSettings(props) {
  return (
    <DisablingMaterialIcon {...props}>
      <RoundedPowerSettings />
    </DisablingMaterialIcon>
  );
}

export default IconPowerSettings;
