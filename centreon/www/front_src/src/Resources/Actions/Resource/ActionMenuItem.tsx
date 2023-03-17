import { useTranslation } from 'react-i18next';

import { MenuItem, MenuItemProps, Tooltip } from '@mui/material';

import { labelActionNotPermitted } from '../../translatedLabels';

type Props = {
  label: string;
  permitted: boolean;
  testId: string;
} & Pick<MenuItemProps, 'onClick' | 'disabled'>;

const ActionMenuItem = ({
  permitted,
  label,
  testId,
  onClick,
  disabled
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const title = permitted ? '' : t(labelActionNotPermitted);

  return (
    <Tooltip title={title}>
      <div>
        <MenuItem data-testid={testId} disabled={disabled} onClick={onClick}>
          {t(label)}
        </MenuItem>
      </div>
    </Tooltip>
  );
};

export default ActionMenuItem;
