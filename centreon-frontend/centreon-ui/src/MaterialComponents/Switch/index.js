import React from 'react';
import { withStyles } from '@material-ui/core/styles';
import Switch from '@material-ui/core/Switch';

const CustomSwitch = withStyles({
  switchBase: {
    color: '#c7c8c9',
    '&$checked': {
      color: '#0072CE',
    },
    '&$checked + $track': {
      backgroundColor: '#0072CE',
      opacity: '.4'
    },
  },
  checked: {},
  track: {},
})(Switch);

export default function Switches() {
  const [state, setState] = React.useState({
    checkedB: true,
  });

  const handleChange = name => event => {
    setState({ ...state, [name]: event.target.checked });
  };

  return (
    <CustomSwitch
      checked={state.checkedB}
      onChange={handleChange('checkedB')}
      value="checkedB"
      color="primary"
    />
  );
}
