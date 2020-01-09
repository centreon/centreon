import React, { forwardRef } from 'react';
import PropTypes from 'prop-types';
import clsx from 'clsx';
import { makeStyles } from '@material-ui/core/styles';
import MuiTextField from '@material-ui/core/TextField';

const useStyles = makeStyles((theme) => ({
  root: {
    backgroundColor: theme.palette.common.white,
    border: `1px solid ${theme.palette.grey[400]}`,
    borderBottom: 0,
  },
  input: {
    paddingLeft: 8,
    paddingBottom: 4,
  },
  inputHelperText: {
    marginTop: 4,
    textAlign: 'right',
  },
}));

const TextField = forwardRef(function TextField(
  { InputProps, InputLabelProps, FormHelperTextProps, ...other },
  ref,
) {
  const classes = useStyles();

  return (
    <MuiTextField
      InputProps={{
        ...InputProps,
        classes: {
          ...InputProps.classes,
          root: clsx(
            classes.root,
            InputProps.classes && InputProps.classes.root
              ? InputProps.classes.root
              : null,
          ),
          input: clsx(
            classes.input,
            InputProps.classes && InputProps.classes.input
              ? InputProps.classes.input
              : null,
          ),
        },
      }}
      InputLabelProps={{
        ...InputLabelProps,
        shrink: true,
      }}
      FormHelperTextProps={{
        ...FormHelperTextProps,
        classes: {
          root: clsx(
            classes.inputHelperText,
            FormHelperTextProps.classes && FormHelperTextProps.classes.root
              ? FormHelperTextProps.classes.root
              : null,
          ),
        },
      }}
      ref={ref}
      {...other}
    />
  );
});

TextField.propTypes = {
  InputProps: PropTypes.objectOf(PropTypes.any),
  InputLabelProps: PropTypes.objectOf(PropTypes.any),
  FormHelperTextProps: PropTypes.objectOf(PropTypes.any),
};

TextField.defaultProps = {
  InputProps: {},
  InputLabelProps: {},
  FormHelperTextProps: {},
};

export default TextField;
