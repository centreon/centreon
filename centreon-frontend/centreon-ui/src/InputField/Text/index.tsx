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
  input: {
    fontSize: theme.typography.body1.fontSize,
  },
  noLabelInput: {
    padding: theme.spacing(1.25),
  },
  compact: {
    padding: theme.spacing(0.75),
    fontSize: 'x-small',
  },
  small: {
    padding: theme.spacing(0.75),
    fontSize: 'small',
  },
  transparent: {
    backgroundColor: 'transparent',
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
  size?: 'small' | 'compact';
  ariaLabel?: string;
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
        ref={ref}
        label={label}
        error={!isNil(error)}
        helperText={error}
        InputProps={{
          className: clsx({
            [classes.transparent]: transparent,
          }),
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
          'aria-label': ariaLabel,
          className: clsx(classes.input, {
            [classes.noLabelInput]: !label && not(isSizeEqualTo(Size.compact)),
            [classes.small]: isSizeEqualTo(Size.small),
            [classes.compact]: isSizeEqualTo(Size.compact),
          }),
        }}
        variant="filled"
        size="small"
        {...rest}
      />
    );
  },
);

export default TextField;
