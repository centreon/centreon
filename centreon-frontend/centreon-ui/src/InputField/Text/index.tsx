import React from 'react';

import clsx from 'clsx';
import { equals, isNil, not } from 'ramda';

import {
  TextField as MuiTextField,
  InputAdornment,
  TextFieldProps,
  Theme,
  makeStyles,
} from '@material-ui/core';

enum Size {
  compact = 'compact',
  small = 'small',
}

const useStyles = makeStyles((theme: Theme) => ({
  compact: {
    fontSize: 'x-small',
    padding: theme.spacing(0.75),
  },
  input: {
    fontSize: theme.typography.body1.fontSize,
  },
  noLabelInput: {
    padding: theme.spacing(1.25),
  },
  small: {
    fontSize: 'small',
    padding: theme.spacing(0.75),
  },
  transparent: {
    backgroundColor: 'transparent',
  },
}));

interface OptionalLabelInputAdornmentProps {
  children: React.ReactNode;
  label?: React.ReactNode;
  position: 'end' | 'start';
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
  EndAdornment?: React.SFC;
  StartAdornment?: React.SFC;
  ariaLabel?: string;
  error?: string;
  size?: 'small' | 'compact';
  transparent?: boolean;
} & Omit<TextFieldProps, 'variant' | 'size' | 'error'>;

const TextField = React.forwardRef(
  (
    {
      StartAdornment,
      EndAdornment,
      label,
      error,
      ariaLabel,
      transparent = false,
      size,
      ...rest
    }: Props,
    ref: React.ForwardedRef<HTMLDivElement>,
  ): JSX.Element => {
    const classes = useStyles();

    const isSizeEqualTo = (sizeToCompare: Size) => equals(size, sizeToCompare);

    return (
      <MuiTextField
        InputProps={{
          className: clsx({
            [classes.transparent]: transparent,
          }),
          disableUnderline: true,
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
        }}
        error={!isNil(error)}
        helperText={error}
        inputProps={{
          ...rest.inputProps,
          'aria-label': ariaLabel,
          className: clsx(classes.input, {
            [classes.noLabelInput]: !label && not(isSizeEqualTo(Size.compact)),
            [classes.small]: isSizeEqualTo(Size.small),
            [classes.compact]: isSizeEqualTo(Size.compact),
          }),
        }}
        label={label}
        ref={ref}
        size="small"
        variant="filled"
        {...rest}
      />
    );
  },
);

export default TextField;
