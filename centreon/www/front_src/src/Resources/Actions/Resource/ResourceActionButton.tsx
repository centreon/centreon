import { useTranslation } from 'react-i18next';

import { Tooltip, useMediaQuery, useTheme } from '@mui/material';

import { IconButton } from '@centreon/ui';

import ActionButton from '../ActionButton';
import { labelActionNotPermitted } from '../../translatedLabels';

import useMediaQueryListing from './useMediaQueryListing';

interface Props {
  disabled: boolean;
  icon: JSX.Element;
  label: string;
  onClick: (event) => void;
  permitted?: boolean;
  testId: string;
}

const ResourceActionButton = ({
  icon,
  label,
  onClick,
  disabled,
  testId,
  permitted = true
}: Props): JSX.Element => {
  const theme = useTheme();
  const { t } = useTranslation();

  const { applyBreakPoint } = useMediaQueryListing();

  const displayCondensed =
    Boolean(useMediaQuery(theme.breakpoints.down(1024))) || applyBreakPoint;

  const title = permitted ? label : `${label} (${t(labelActionNotPermitted)})`;

  if (displayCondensed) {
    return (
      <IconButton
        ariaLabel={t(label) as string}
        data-testid={testId}
        disabled={disabled}
        size="large"
        title={title}
        onClick={onClick}
      >
        {icon}
      </IconButton>
    );
  }

  return (
    <Tooltip title={permitted ? '' : labelActionNotPermitted}>
      <span>
        <ActionButton
          aria-label={t(label) as string}
          data-testid={testId}
          disabled={disabled}
          startIcon={icon}
          variant="contained"
          onClick={onClick}
        >
          {label}
        </ActionButton>
      </span>
    </Tooltip>
  );
};

export default ResourceActionButton;
