import { forwardRef, useCallback } from 'react';

import { equals, isNil } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import {
  TextField as MuiTextField,
  InputAdornment,
  TextFieldProps,
  Theme,
  Tooltip,
  Typography,
  Box
} from '@mui/material';

import { getNormalizedId } from '../../utils';

import useAutoSize from './useAutoSize';

const useStyles = makeStyles<{ displayAsBlock: boolean }>()(
  (theme: Theme, { displayAsBlock }) => ({
    autoSizeCompact: {
      paddingRight: theme.spacing(1),
      paddingTop: theme.spacing(0.6)
    },
    hiddenText: {
      display: 'table',
      lineHeight: 0,
      transform: 'scaleY(0)'
    },
    input: {
      fontSize: theme.typography.body1.fontSize
    },
    inputBase: {
      display: displayAsBlock ? 'block' : 'inline-flex',
      justifyItems: 'start',
      paddingRight: theme.spacing(1)
    },
    noLabelInput: {
      padding: theme.spacing(1)
    },
    textField: {
      transition: theme.transitions.create(['width'], {
        duration: theme.transitions.duration.shortest
      })
    },
    transparent: {
      backgroundColor: 'transparent'
    }
  })
);

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
  autoSize?: boolean;
  autoSizeCustomPadding?: number;
  autoSizeDefaultWidth?: number;
  className?: string;
  dataTestId: string;
  debounced?: boolean;
  displayErrorInTooltip?: boolean;
  error?: string;
  externalValueForAutoSize?: string;
  open?: boolean;
  required?: boolean;
  size?: SizeVariant;
  transparent?: boolean;
  value?: string;
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
      autoSize = false,
      debounced = false,
      autoSizeDefaultWidth = 0,
      externalValueForAutoSize,
      autoSizeCustomPadding,
      defaultValue,
      required = false,
      ...rest
    }: Props,
    ref: React.ForwardedRef<HTMLDivElement>
  ): JSX.Element => {
    const { classes, cx } = useStyles({
      displayAsBlock: autoSize && isNil(StartAdornment) && isNil(EndAdornment)
    });

    const { inputRef, width, changeInputValue, innerValue } = useAutoSize({
      autoSize,
      autoSizeCustomPadding,
      autoSizeDefaultWidth,
      value: externalValueForAutoSize || rest.value
    });

    const tooltipTitle = displayErrorInTooltip && !isNil(error) ? error : '';

    const getValueProps = useCallback((): object => {
      if (debounced) {
        return {};
      }

      if (defaultValue) {
        return { defaultValue };
      }

      return { value: innerValue };
    }, [innerValue, debounced, defaultValue]);

    return (
      <Box sx={{ width: autoSize ? 'auto' : '100%' }}>
        <Tooltip placement="top" title={tooltipTitle}>
          <MuiTextField
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
            onChange={changeInputValue}
            {...getValueProps()}
            {...rest}
            InputProps={{
              className: cx(
                classes.inputBase,
                {
                  [classes.transparent]: transparent,
                  [classes.autoSizeCompact]: autoSize && equals(size, 'compact')
                },
                className
              ),
              endAdornment: (
                <OptionalLabelInputAdornment label={label} position="end">
                  {EndAdornment ? (
                    <EndAdornment />
                  ) : (
                    rest.InputProps?.endAdornment
                  )}
                </OptionalLabelInputAdornment>
              ),
              startAdornment: StartAdornment && (
                <OptionalLabelInputAdornment label={label} position="start">
                  <StartAdornment />
                </OptionalLabelInputAdornment>
              ),
              ...rest.InputProps
            }}
            className={classes.textField}
            required={required}
            sx={{
              width: autoSize ? width : undefined,
              ...rest?.sx
            }}
          />
        </Tooltip>
        {autoSize && (
          <Typography className={classes.hiddenText} ref={inputRef}>
            {rest.value || externalValueForAutoSize || innerValue}
          </Typography>
        )}
      </Box>
    );
  }
);

export default TextField;
