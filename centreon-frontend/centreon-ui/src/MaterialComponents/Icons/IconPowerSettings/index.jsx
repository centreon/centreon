import React from 'react';

import PowerSettings from '@material-ui/icons/PowerSettingsNew';
import MaterialIcon from '../MaterialIcon';

import RoundedInvertedIcon from '../RoundedInvertedIcon';

const RoundedInvertedPowerSettings = RoundedInvertedIcon(PowerSettings);

function IconPowerSettings(props) {
  return (
    <MaterialIcon {...props}>
      <RoundedInvertedPowerSettings />
    </MaterialIcon>
  );
}

export default IconPowerSettings;
