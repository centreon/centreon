/* eslint-disable react/jsx-filename-extension */

import { withStyles } from '@material-ui/core/styles';
import Switch from '@material-ui/core/Switch';

const styles = () => ({
  switchBase: {
    color: '#c7c8c9',
    '&$checked': {
      color: '#0072CE',
      '&:hover': {
        backgroundColor: 'rgba(0, 114, 206, 0.08)',
      },
    },
    '&$checked + $track': {
      backgroundColor: '#0072CE',
      opacity: '.4',
    },
  },
  checked: {},
  track: {},
});

export default withStyles(styles)(Switch);
