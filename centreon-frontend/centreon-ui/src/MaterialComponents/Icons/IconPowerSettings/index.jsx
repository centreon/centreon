import React from 'react';

import PowerSettings from '@material-ui/icons/PowerSettingsNew';
import MaterialIcon from '../MaterialIcon';

import RoundedInvertedIcon from '../RoundedInvertedIcon';

const RoundedInvertedPowerSettings = RoundedInvertedIcon(PowerSettings);

function IconPowerSettings(props) {
  return (
    <MaterialIcon {...props}  aria-label="icon enable disable">
      <RoundedInvertedPowerSettings />
    </MaterialIcon>
  );
}

export default IconPowerSettings;
