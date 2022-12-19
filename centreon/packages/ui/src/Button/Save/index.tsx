import { any, isEmpty, isNil, not, or, pipe } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Theme, Tooltip } from '@mui/material';
import { LoadingButton } from '@mui/lab';

import StartIcon from './StartIcon';
import Content from './Content';

const useStyles = makeStyles()((theme: Theme) => ({
  loadingButton: {
    width: theme.spacing(5)
  }
}));

interface Props extends Record<string, unknown> {
  className?: string;
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

const SaveButton = ({
  succeeded = false,
  loading = false,
  tooltipLabel = '',
  labelSucceeded = '',
  labelLoading = '',
  labelSave = '',
  size = 'small',
  className,
  ...rest
}: Props): JSX.Element => {
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
          loading={loading}
          loadingPosition={labelLoading ? 'start' : 'center'}
          size={size}
          startIcon={<StartIcon startIconConfig={startIconConfig} />}
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
