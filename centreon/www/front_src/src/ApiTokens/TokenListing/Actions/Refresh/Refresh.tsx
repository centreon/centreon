import { useTranslation } from 'react-i18next';

import IconRefresh from '@mui/icons-material/Refresh';

import { IconButton } from '@centreon/ui';

import { labelRefresh } from '../../../../Resources/translatedLabels';

interface Props {
  isLoading: boolean;
  onRefresh: () => void;
}

const Refresh = ({ onRefresh, isLoading }: Props): JSX.Element => {
  const { t } = useTranslation();

  return (
    <IconButton
      ariaLabel={t(labelRefresh) as string}
      data-testid="Refresh"
      disabled={isLoading}
      size="small"
      title={t(labelRefresh) as string}
      onClick={onRefresh}
    >
      <IconRefresh />
    </IconButton>
  );
};

export default Refresh;
