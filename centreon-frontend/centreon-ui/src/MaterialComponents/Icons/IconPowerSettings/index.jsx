import React from 'react';
import PowerSettings from '@material-ui/icons/PowerSettingsNew';
import styled from '@emotion/styled';
import MaterialIcon from '../MaterialIcon';

const InvertedPowerSettingsIcon = styled(PowerSettings)(() => ({
  color: '#FFF',
  backgroundColor: '#707070',
  borderRadius: '50%',
  padding: 3,
  fontSize: 15,
}));

function IconPowerSettings(props) {
  return (
    <MaterialIcon {...props}>
      <InvertedPowerSettingsIcon />
    </MaterialIcon>
  );
}

export default IconPowerSettings;
