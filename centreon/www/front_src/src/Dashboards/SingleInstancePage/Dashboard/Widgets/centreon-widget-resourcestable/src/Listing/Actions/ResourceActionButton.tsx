import { useTranslation } from 'react-i18next';

import { IconButton } from '@centreon/ui';

import { labelActionNotPermitted } from '../translatedLabels';

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
  const { t } = useTranslation();

  const title = permitted ? label : `${label} (${t(labelActionNotPermitted)})`;

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
};

export default ResourceActionButton;
