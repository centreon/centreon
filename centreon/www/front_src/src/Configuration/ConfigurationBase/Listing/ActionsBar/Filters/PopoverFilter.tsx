import { Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import TuneIcon from '@mui/icons-material/Tune';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

import { labelFilters } from '../../../translatedLabels';

import AdditionalFilters from './Filters/Filters';

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
        {(): JSX.Element => <AdditionalFilters />}
      </PopoverMenu>
    </Suspense>
  );
};

export default PopoverFilter;
