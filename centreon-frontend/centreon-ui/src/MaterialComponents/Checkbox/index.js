/* eslint-disable react/jsx-filename-extension */
/* eslint-disable react/prop-types */
/* eslint-disable react/prefer-stateless-function */

import React, { Component } from 'react';
import Checkbox from '@material-ui/core/Checkbox';
import { withStyles } from '@material-ui/core/styles';
import FormControlLabel from '@material-ui/core/FormControlLabel';

const CustomCheckbox = withStyles({
  root: {
    color: '#0072CE',
    '&$checked': {
      color: '#0072CE',
    },
    fontSize: 10,
  },
  checked: {},
  label: {
    fontSize: 10,
  },
})((props) => <Checkbox color="default" {...props} />);

// eslint-disable-next-line no-unused-vars
class CheckboxDefault extends Component {
  render() {
    const { label } = this.props;
    return <FormControlLabel control={<CustomCheckbox />} label={label} />;
  }
}

export default CheckboxDefault;
