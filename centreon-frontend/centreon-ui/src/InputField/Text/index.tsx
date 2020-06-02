import React from 'react';

import clsx from 'clsx';
import { isNil } from 'ramda';

import {
  TextField as MuiTextField,
  InputAdornment,
  TextFieldProps,
  Theme,
  makeStyles,
} from '@material-ui/core';

const useStyles = makeStyles((theme: Theme) => ({
  noLabelInput: {
    padding: theme.spacing(1.5),
  },
}));

interface OptionalLabelInputAdornmentProps {
  label?: React.ReactNode;
  position: 'end' | 'start';
  children: React.ReactNode;
}

const OptionalLabelInputAdornment = ({
  label,
  position,
  children,
}: OptionalLabelInputAdornmentProps): JSX.Element => {
  const noMarginWhenNoLabel = !label && { style: { marginTop: 0 } };

  return (
    <InputAdornment {...noMarginWhenNoLabel} position={position}>
      {children}
    </InputAdornment>
  );
};

export type Props = {
  StartAdornment?: React.SFC;
  EndAdornment?: React.SFC;
  error?: string;
} & Omit<TextFieldProps, 'variant' | 'size' | 'error'>;

const TextField = ({
  StartAdornment,
  EndAdornment,
  label,
  error,
  ...rest
}: Props): JSX.Element => {
  const classes = useStyles();

  return (
    <MuiTextField
      label={label}
      error={!isNil(error)}
      helperText={error}
      InputProps={{
        endAdornment: EndAdornment && (
          <OptionalLabelInputAdornment label={label} position="end">
            <EndAdornment />
          </OptionalLabelInputAdornment>
        ),
        startAdornment: StartAdornment && (
          <OptionalLabelInputAdornment label={label} position="start">
            <StartAdornment />
          </OptionalLabelInputAdornment>
        ),
        disableUnderline: true,
      }}
      inputProps={{
        className: clsx({ [classes.noLabelInput]: !label }),
      }}
      variant="filled"
      size="small"
      {...rest}
    />
  );
};

export default TextField;
