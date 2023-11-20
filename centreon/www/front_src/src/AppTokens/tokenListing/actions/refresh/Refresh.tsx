import { useState } from 'react';

import { useTranslation } from 'react-i18next';

import IconRefresh from '@mui/icons-material/Refresh';

import { IconButton } from '@centreon/ui';

import { labelRefresh } from '../../../../Resources/translatedLabels';
import { useTokenListing } from '../../useTokenListing';

const Refresh = (): JSX.Element => {
  const { t } = useTranslation();
  const [refresh, setRefresh] = useState(false);
  useTokenListing({ refresh });

  const onRefresh = (): void => {
    setRefresh(!refresh);
  };

  return (
    <IconButton
      ariaLabel={t(labelRefresh) as string}
      data-testid="Refresh"
      //   disabled={sending}
      size="small"
      title={t(labelRefresh) as string}
      onClick={onRefresh}
    >
      <IconRefresh />
    </IconButton>
  );
};

export default Refresh;
