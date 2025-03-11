// import { Suspense } from 'react';

// import { useTranslation } from 'react-i18next';

// import TuneIcon from '@mui/icons-material/Tune';

// import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

// import { labelSearchOptions } from '../../../translatedLabels';
// import { useStyles } from '../actions.styles';

// import Filter from './Filter';

// const TokenFilter = (): JSX.Element => {
//   const { classes } = useStyles();
//   const { t } = useTranslation();

//   return (
//     <Suspense
//       fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
//     >
//       <PopoverMenu
//         className={classes.popoverMenu}
//         dataTestId={labelSearchOptions}
//         icon={<TuneIcon fontSize="small" />}
//         popperPlacement="bottom-end"
//         popperProps={{ className: classes.popoverMenu }}
//         title={t(labelSearchOptions) as string}
//       >
//         {(): JSX.Element => <Filter />}
//       </PopoverMenu>
//     </Suspense>
//   );
// };

// export default TokenFilter;

import { Suspense } from 'react';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';
import TuneIcon from '@mui/icons-material/Tune';
import { Badge } from '@mui/material';
import { filter, length, pipe, toPairs } from 'ramda';
import { useTranslation } from 'react-i18next';
import { labelFilters } from '../../../translatedLabels';
import Filters from './Filter';
import { useStyles } from './Filters.styles';

const countDifferences = (defaultValues, values) =>
  pipe(
    toPairs,
    filter(([key, val]) => val !== values[key]),
    length
  )(defaultValues);

const PopoverFilter = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useStyles();

  // const configuration = useAtomValue(configurationAtom);
  // const filters = useAtomValue(filtersAtom);
  // const initialValues = configuration?.filtersInitialValues;

  // const changedFiltersCount = countDifferences(initialValues, filters);

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <Badge color="primary" badgeContent={2} className={classes.badge}>
        <PopoverMenu
          dataTestId={labelFilters}
          icon={<TuneIcon fontSize="small" />}
          popperPlacement="bottom-end"
          title={t(labelFilters)}
        >
          {(): JSX.Element => <Filters />}
        </PopoverMenu>
      </Badge>
    </Suspense>
  );
};

export default PopoverFilter;
