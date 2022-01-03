import React from 'react';

import { any, equals, isEmpty, isNil, not, or, pipe } from 'ramda';

import { Button, Tooltip } from '@mui/material';

import StartIcon from './StartIcon';
import Content from './Content';

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
  const hasLabel = hasValue([labelLoading, labelSave, labelSucceeded]);
  const isSmall = equals('small', size);

  const startIconConfig = {
    hasLabel,
    loading,
    succeeded,
  } as StartIconConfigProps;

  const style = hasLabel ? baseStyle : { ...baseStyle, width: 40 };

  return (
    <Tooltip placement="bottom" title={tooltipLabel}>
      <div>
        <Button
          aria-label="save button"
          color="primary"
          size={size}
          startIcon={
            <StartIcon
              iconSize={iconSize}
              isSmall={isSmall}
              smallIconSize={smallIconSize}
              startIconConfig={startIconConfig}
            />
          }
          style={not(isSmall) ? style : undefined}
          variant="contained"
          {...rest}
        >
          {Content({
            iconSize,
            isSmall,
            labelLoading,
            labelSave,
            labelSucceeded,
            loading,
            smallIconSize,
            succeeded,
          })}
        </Button>
      </div>
    </Tooltip>
  );
};

export default SaveButton;
