import { forwardRef, useMemo } from 'react';

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

type SizeVariant = 'large' | 'medium' | 'small' | 'compact';

export type Props = {
  EndAdornment?: React.FC;
  StartAdornment?: React.FC;
  ariaLabel?: string;
  className?: string;
  dataTestId: string;
  displayErrorInTooltip?: boolean;
  error?: string;
  open?: boolean;
  size?: SizeVariant;
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

    const baseProps = useMemo(
      () => ({
        'data-testid': dataTestId,
        error: !isNil(error),
        helperText: displayErrorInTooltip ? undefined : error,
        id: getNormalizedId(dataTestId || ''),
        label,
        ref,
        size: size || 'small'
      }),
      [label, ref, size, dataTestId, error, displayErrorInTooltip]
    );

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
            )
          }}
          data-testid={dataTestId}
          id={getNormalizedId(dataTestId || '')}
          inputProps={{
            ...rest.inputProps,
            'aria-label': ariaLabel,
            'data-testid': dataTestId || ''
          }}
          variant="outlined"
          {...baseProps}
          {...rest}
        />
      </Tooltip>
    );
  }
);

export default TextField;
