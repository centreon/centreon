import { useTranslation } from 'react-i18next';

import { SearchField } from '@centreon/ui';

import { labelSearch } from '../../../translatedLabels';
import { renderEndAdornmentFilter } from '../../../../Resources/Filter';

const TokenSearch = (): JSX.Element => {
  const { t } = useTranslation();

  const clearFilters = (): void => {};

  return (
    <SearchField
      fullWidth
      EndAdornment={renderEndAdornmentFilter(clearFilters)}
      dataTestId={labelSearch}
      placeholder={t(labelSearch) as string}
      value=""
    />
  );
};

export default TokenSearch;
