import { useTranslation } from 'react-i18next';

import IconRefresh from '@mui/icons-material/Refresh';

import { IconButton } from '@centreon/ui';
import { labelRefresh } from '../../../translatedLabels';

const Refresh = (): JSX.Element => {
  const { t } = useTranslation();

  const onRefresh = (): void => undefined;
  const isLoading = false;

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
