import { any, isEmpty, isNil, not, or, pipe } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { LoadingButton, LoadingButtonProps } from '@mui/lab';
import { Theme, Tooltip } from '@mui/material';

import { getNormalizedId } from '../../utils';

import Content from './Content';
import StartIcon from './StartIcon';

const useStyles = makeStyles()((theme: Theme) => ({
  loadingButton: {
    width: theme.spacing(5)
  }
}));

interface Props {
  className?: string;
  labelLoading?: string;
  labelSave?: string;
  labelSucceeded?: string;
  loading?: boolean;
  size?: 'small' | 'medium' | 'large';
  startIcon?: boolean;
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

const SaveButton = ({
  succeeded = false,
  loading = false,
  tooltipLabel = '',
  labelSucceeded = '',
  labelLoading = '',
  labelSave = '',
  size = 'small',
  className,
  startIcon = true,
  ...rest
}: Props & LoadingButtonProps): JSX.Element => {
  const { classes, cx } = useStyles();
  const hasLabel = hasValue([labelLoading, labelSave, labelSucceeded]);

  const startIconConfig = {
    hasLabel,
    loading,
    succeeded
  } as StartIconConfigProps;

  return (
    <Tooltip placement="bottom" title={tooltipLabel}>
      <div>
        <LoadingButton
          aria-label="save button"
          className={cx(
            {
              [classes.loadingButton]: !hasLabel
            },
            className
          )}
          color="primary"
          data-testid={labelSave}
          id={getNormalizedId(labelSave)}
          loading={loading}
          loadingPosition={labelLoading ? 'start' : 'center'}
          size={size}
          startIcon={
            startIcon && <StartIcon startIconConfig={startIconConfig} />
          }
          variant="contained"
          {...rest}
        >
          {Content({
            labelLoading,
            labelSave,
            labelSucceeded,
            loading,
            succeeded
          })}
        </LoadingButton>
      </div>
    </Tooltip>
  );
};

export default SaveButton;
