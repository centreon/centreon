import { Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import TuneIcon from '@mui/icons-material/Tune';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

import { labelMoreFilters } from '../../../translatedLabels';

import AdditionalFilters from './AdvancedFilters';

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <PopoverMenu
        dataTestId={labelMoreFilters}
        icon={<TuneIcon fontSize="small" />}
        popperPlacement="bottom-end"
        title={t(labelMoreFilters) as string}
      >
        {(): JSX.Element => <AdditionalFilters />}
      </PopoverMenu>
    </Suspense>
  );
};

export default PopoverFilter;
