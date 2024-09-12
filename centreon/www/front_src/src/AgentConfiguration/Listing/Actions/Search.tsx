import { SearchField } from '@centreon/ui';
import { useTranslation } from 'react-i18next';
import { labelSearch } from '../../translatedLabels';
import { useActionsStyles } from './Actions.styles';
import { useSearch } from './useSearch';

const Search = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const { change } = useSearch();

  return (
    <SearchField
      className={classes.search}
      debounced
      fullWidth
      dataTestId={labelSearch}
      placeholder={t(labelSearch)}
      onChange={change}
    />
  );
};

export default Search;
