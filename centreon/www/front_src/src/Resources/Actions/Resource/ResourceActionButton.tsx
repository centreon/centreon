import { useTranslation } from 'react-i18next';

import { Tooltip } from '@mui/material';

import { IconButton } from '@centreon/ui';

import { labelActionNotPermitted } from '../../translatedLabels';
import ActionButton from '../ActionButton';

interface Props {
  disabled: boolean;
  displayCondensed?: boolean;
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
  permitted = true,
  displayCondensed = false
}: Props): JSX.Element => {
  const { t } = useTranslation();

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
