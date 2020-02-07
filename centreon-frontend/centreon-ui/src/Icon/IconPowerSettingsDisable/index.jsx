import React from 'react';

import { styled } from '@material-ui/core/styles';
import PowerSettings from '@material-ui/icons/PowerSettingsNew';

import MaterialIcon from '../MaterialIcon';
import RoundedInvertedIcon from '../RoundedInvertedIcon';

const RoundedInvertedPowerSettings = RoundedInvertedIcon(PowerSettings);

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

const IconPowerSettings = (props) => (
  <DisablingMaterialIcon {...props} aria-label="icon enable disable inactive">
    <RoundedInvertedPowerSettings />
  </DisablingMaterialIcon>
);

export default IconPowerSettings;
