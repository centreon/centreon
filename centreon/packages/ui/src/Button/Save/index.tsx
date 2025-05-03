import { makeStyles } from 'tss-react/mui';

import { Theme, Tooltip } from '@mui/material';

import { getNormalizedId } from '../../utils';

import { Button, ButtonProps } from '../../components';
import { useSave } from './useSave';

const useStyles = makeStyles()((theme: Theme) => ({
  loadingButton: {
    width: theme.spacing(5)
  }
}));

export interface Props {
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
}: Props & Omit<ButtonProps, 'children'>): JSX.Element => {
  const { classes, cx } = useStyles();

  const { content, startIconToDisplay, hasLabel } = useSave({
    labelLoading,
    labelSave,
    labelSucceeded,
    loading,
    succeeded,
    startIcon
  });

  return (
    <Tooltip placement="bottom" title={tooltipLabel}>
      <div>
        <Button
          aria-label="save button"
          className={cx(
            {
              [classes.loadingButton]: !hasLabel
            },
            className
          )}
          data-testid={labelSave}
          id={getNormalizedId({ idToNormalize: labelSave })}
          loading={loading}
          loadingPosition={labelLoading ? 'start' : undefined}
          size={size}
          startIcon={startIconToDisplay}
          variant="primary"
          {...rest}
        >
          {content}
        </Button>
      </div>
    </Tooltip>
  );
};

export default SaveButton;
