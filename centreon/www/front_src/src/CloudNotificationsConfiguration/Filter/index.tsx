import { useRef } from 'react';

import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';
import { useSetAtom } from 'jotai';

import debounce from '@mui/utils/debounce';

import { SearchField } from '@centreon/ui';

import { searchAtom } from '../atom';
import { labelSearch } from '../translatedLabels';

const useStyle = makeStyles()((theme) => ({
  search: {
    width: theme.spacing(50)
  }
}));

const Filter = (): JSX.Element => {
  const { t } = useTranslation();

  const { classes } = useStyle();

  const serSearchVAlue = useSetAtom(searchAtom);

  const searchDebounced = useRef(
    debounce<(search) => void>((debouncedSearch): void => {
      serSearchVAlue(debouncedSearch);
    }, 500)
  );

  const onChange = ({ target }): void => {
    searchDebounced.current(target.value);
  };

  return (
    <SearchField
      debounced
      className={classes.search}
      dataTestId={t(labelSearch)}
      placeholder={t(labelSearch)}
      onChange={onChange}
    />
  );
};

export default Filter;
