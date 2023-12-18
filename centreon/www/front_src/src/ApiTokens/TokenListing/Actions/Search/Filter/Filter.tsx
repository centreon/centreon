import { Suspense } from 'react';

import { useTranslation } from 'react-i18next';

import TuneIcon from '@mui/icons-material/Tune';

import { LoadingSkeleton, PopoverMenu } from '@centreon/ui';

import { labelSearchOptions } from '../../../../translatedLabels';
import { useStyles } from '../../actions.styles';

const TokenFilter = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  return (
    <Suspense
      fallback={<LoadingSkeleton height={24} variant="circular" width={24} />}
    >
      <PopoverMenu
        className={classes.spacing}
        dataTestId={labelSearchOptions}
        icon={<TuneIcon fontSize="small" />}
        popperPlacement="bottom-start"
        title={t(labelSearchOptions) as string}
      >
        {(): JSX.Element => <div />}
      </PopoverMenu>
    </Suspense>
  );
};

export default TokenFilter;
