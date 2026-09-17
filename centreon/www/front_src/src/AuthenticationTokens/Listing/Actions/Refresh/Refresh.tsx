import IconRefresh from '@mui/icons-material/Refresh';

import { IconButton } from '@centreon/ui';

import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';

import { labelRefresh } from '../../../translatedLabels';

const Refresh = (): JSX.Element => {
  const { t } = useTranslation();

  const queryClient = useQueryClient();

  const onRefresh = (): void => {
    queryClient.invalidateQueries({ queryKey: ['listTokens'] });
  };

  return (
    <IconButton
      ariaLabel={t(labelRefresh) as string}
      data-testid="Refresh"
      onClick={onRefresh}
      size="small"
      title={t(labelRefresh) as string}
    >
      <IconRefresh />
    </IconButton>
  );
};

export default Refresh;
