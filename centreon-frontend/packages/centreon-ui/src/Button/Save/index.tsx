import React from 'react';

import { useTranslation } from 'react-i18next';
import {
  always,
  any,
  cond,
  equals,
  isEmpty,
  isNil,
  not,
  or,
  pipe,
  propEq,
  T,
} from 'ramda';

import { Button, CircularProgress, Tooltip } from '@material-ui/core';
import CheckIcon from '@material-ui/icons/Check';
import SaveIcon from '@material-ui/icons/Save';

interface Props extends Record<string, unknown> {
  labelLoading?: string;
  labelSave?: string;
  labelSucceeded?: string;
  loading?: boolean;
  size?: 'small' | 'medium' | 'large';
  succeeded?: boolean;
  tooltip?: string;
  tooltipLabel?: string;
}

interface StartIconConfigProps {
  hasLabel: boolean;
  loading: boolean;
  succeeded: boolean;
}

const isNilOrEmpty = (value): boolean => or(isNil(value), isEmpty(value));
const hasValue = any(pipe(isNilOrEmpty, not));
const iconSize = 30;
const smallIconSize = 20;

const baseStyle = { height: 40 };

const SaveButton = ({
  succeeded = false,
  loading = false,
  tooltipLabel = '',
  labelSucceeded = '',
  labelLoading = '',
  labelSave = '',
  size = 'medium',
  ...rest
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const hasLabel = hasValue([labelLoading, labelSave, labelSucceeded]);
  const isSmall = equals('small', size);

  const startIconConfig = {
    hasLabel,
    loading,
    succeeded,
  } as StartIconConfigProps;

  const ButtonStartIcon = (): JSX.Element | null =>
    cond<StartIconConfigProps, JSX.Element | null>([
      [pipe(propEq('hasLabel', true), not), always(null)],
      [propEq('succeeded', true), always(<CheckIcon />)],
      [
        propEq('loading', true),
        always(
          <CircularProgress
            color="inherit"
            size={isSmall ? smallIconSize : iconSize}
          />,
        ),
      ],
      [T, always(<SaveIcon />)],
    ])(startIconConfig);

  const ButtonContent = (): JSX.Element | string => {
    if (succeeded) {
      return labelSucceeded ? t(labelSucceeded) : <CheckIcon />;
    }

    if (loading) {
      return labelLoading ? (
        t(labelLoading)
      ) : (
        <CircularProgress
          color="inherit"
          size={isSmall ? smallIconSize : iconSize}
        />
      );
    }

    return labelSave ? t(labelSave) : <SaveIcon />;
  };

  const style = hasLabel ? baseStyle : { ...baseStyle, width: 40 };

  return (
    <Tooltip placement="bottom" title={tooltipLabel}>
      <div>
        <Button
          aria-label="save button"
          color="primary"
          size={size}
          startIcon={<ButtonStartIcon />}
          style={not(isSmall) ? style : undefined}
          variant="contained"
          {...rest}
        >
          {ButtonContent()}
        </Button>
      </div>
    </Tooltip>
  );
};

export default SaveButton;
