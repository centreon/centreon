import { Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { useTranslation } from 'react-i18next';
import { labelFilters } from '../../translatedLabels';
import Filters from './Filters';

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <PopoverMenu
        dataTestId={labelFilters}
        icon={<TuneIcon fontSize="small" />}
        popperPlacement="bottom-end"
        title={t(labelFilters)}
      >
        {(): JSX.Element => <Filters />}
      </PopoverMenu>
    </Suspense>
  );
};

export default PopoverFilter;
