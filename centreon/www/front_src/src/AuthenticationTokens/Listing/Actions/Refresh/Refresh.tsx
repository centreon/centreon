import { useTranslation } from 'react-i18next';

import IconRefresh from '@mui/icons-material/Refresh';

import { IconButton } from '@centreon/ui';
import { useQueryClient } from '@tanstack/react-query';
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
      size="small"
      title={t(labelRefresh) as string}
      onClick={onRefresh}
    >
      <IconRefresh />
    </IconButton>
  );
};

export default Refresh;
