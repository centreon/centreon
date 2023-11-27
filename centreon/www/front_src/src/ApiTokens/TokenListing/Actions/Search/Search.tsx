import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../../../translatedLabels';
import { renderEndAdornmentFilter } from '../../../../Resources/Filter';
import { useStyles } from '../actions.styles';

const TokenSearch = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const clearFilters = (): void => {};

  return (
    <div className={classes.search}>
      <SearchField
        fullWidth
        EndAdornment={renderEndAdornmentFilter(clearFilters)}
        dataTestId={labelSearch}
        placeholder={t(labelSearch) as string}
        value=""
      />
    </div>
  );
};

export default TokenSearch;
