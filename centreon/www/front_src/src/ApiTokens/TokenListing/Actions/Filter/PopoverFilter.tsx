import { Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import TuneIcon from '@mui/icons-material/Tune';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

import { labelSearchOptions } from '../../../translatedLabels';
import { useStyles } from '../actions.styles';

import Filter from './Filter';

const TokenFilter = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <PopoverMenu
        className={classes.popoverMenu}
        dataTestId={labelSearchOptions}
        icon={<TuneIcon fontSize="small" />}
        popperPlacement="bottom-end"
        popperProps={{ className: classes.popoverMenu }}
        title={t(labelSearchOptions) as string}
      >
        {(): JSX.Element => <Filter />}
      </PopoverMenu>
    </Suspense>
  );
};

export default TokenFilter;
