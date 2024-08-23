import { useState } from 'react';

import { useAtom } from 'jotai';

import { TextField, useDebounce, useMemoComponent } from '@centreon/ui';

import { SearchableFields } from '../../../Criterias/searchQueryLanguage/models';
import { searchAtom } from '../../../filterAtoms';
import { useStyles } from '../../criterias.style';
import { informationLabel } from '../../translatedLabels';
import useSearchInputDataByField from '../../useSearchInputDataByField';
import { replaceValueFromSearchInput } from '../../utils';

import useFilterSearchValue from './useFilterSearch';

interface Props {
  field: SearchableFields;
  placeholder?: string;
}
const FilterSearch = ({ field, placeholder }: Props): JSX.Element => {
  const { classes } = useStyles();
  const [isDirty, setIsDirty] = useState(false);
  const [search, setSearch] = useAtom(searchAtom);

  const { content, fieldInformation } = useSearchInputDataByField({ field });

  const { value, setValue } = useFilterSearchValue({
    content,
    isDirty,
    search
  });

  const debouncedRequest = useDebounce({
    functionToDebounce: (): void => {
      const isFieldExist = search.includes(field);

      if (!value) {
        setSearch(search.replace(fieldInformation, ''));

        return;
      }
      if (isFieldExist) {
        const updatedValue = replaceValueFromSearchInput({
          newContent: `${field}:${value}`,
          search,
          targetField: fieldInformation
        });
        setSearch(updatedValue);

        return;
      }
      const data = search.concat(' ', `${field}:${value}`);
      setSearch(data);
    },

    wait: 300
  });

  const onChange = (e): void => {
    setValue(e.target.value.replace(/\s/g, ''));
    setIsDirty(true);

    debouncedRequest();
  };

  return useMemoComponent({
    Component: (
      <TextField
        className={classes.inputInformation}
        dataTestId={informationLabel}
        placeholder={placeholder}
        value={value}
        onChange={onChange}
        onFocus={() => setIsDirty(true)}
      />
    ),
    memoProps: [search, value]
  });
};

export default FilterSearch;
