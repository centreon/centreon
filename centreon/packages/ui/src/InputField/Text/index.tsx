import { forwardRef } from 'react';

import { isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  TextField as MuiTextField,
  InputAdornment,
  TextFieldProps,
  Theme,
  Tooltip
} from '@mui/material';

import getNormalizedId from '../../utils/getNormalizedId';

const useStyles = makeStyles()((theme: Theme) => ({
  compact: {
    fontSize: 'x-small'
  },
  input: {
    fontSize: theme.typography.body1.fontSize
  },
  noLabelInput: {
    padding: theme.spacing(1)
  },
  transparent: {
    backgroundColor: 'transparent'
  }
}));

interface OptionalLabelInputAdornmentProps {
  children: React.ReactNode;
  label?: React.ReactNode;
  position: 'end' | 'start';
}

const OptionalLabelInputAdornment = ({
  label,
  position,
  children
}: OptionalLabelInputAdornmentProps): JSX.Element => {
  const noMarginWhenNoLabel = !label && { style: { marginTop: 0 } };

  return (
    <InputAdornment {...noMarginWhenNoLabel} position={position}>
      {children}
    </InputAdornment>
  );
};

export type Props = {
  EndAdornment?: React.FC;
  StartAdornment?: React.FC;
  ariaLabel?: string;
  className?: string;
  dataTestId: string;
  displayErrorInTooltip?: boolean;
  error?: string;
  open?: boolean;
  size?: 'large' | 'medium' | 'small' | 'compact';
  transparent?: boolean;
} & Omit<TextFieldProps, 'variant' | 'size' | 'error'>;

const TextField = forwardRef(
  (
    {
      StartAdornment,
      EndAdornment,
      label,
      error,
      ariaLabel,
      dataTestId,
      transparent = false,
      size,
      displayErrorInTooltip = false,
      className,
      ...rest
    }: Props,
    ref: React.ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes, cx } = useStyles();

    const tooltipTitle = displayErrorInTooltip && !isNil(error) ? error : '';

    return (
      <Tooltip placement="top" title={tooltipTitle}>
        <MuiTextField
          InputProps={{
            ...rest.InputProps,
            className: cx(
              {
                [classes.transparent]: transparent
              },
              className
            ),
            endAdornment: EndAdornment && (
              <OptionalLabelInputAdornment label={label} position="end">
                <EndAdornment />
              </OptionalLabelInputAdornment>
            ),
            startAdornment: StartAdornment && (
              <OptionalLabelInputAdornment label={label} position="start">
                <StartAdornment />
              </OptionalLabelInputAdornment>
            )
          }}
          data-testid={dataTestId}
          error={!isNil(error)}
          helperText={displayErrorInTooltip ? undefined : error}
          id={getNormalizedId(dataTestId || '')}
          inputProps={{
            ...rest.inputProps,
            'aria-label': ariaLabel,
            'data-testid': dataTestId
          }}
          label={label}
          ref={ref}
          size={size || 'small'}
          {...rest}
        />
      </Tooltip>
    );
  }
);

export default TextField;
