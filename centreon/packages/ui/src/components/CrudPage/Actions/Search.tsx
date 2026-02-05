import { SearchField } from '@centreon/ui';

import { useActionsStyles } from './Actions.styles';
import Filters from './Filters';
import { useSearch } from './useSearch';

interface Props {
  label: string;
  filters: JSX.Element;
}

const Search = ({ label, filters }: Props): JSX.Element => {
  const { classes } = useActionsStyles();

  const { change } = useSearch();

  return (
    <div className={classes.search}>
      <SearchField
        dataTestId={label}
        debounced
        fullWidth
        onChange={change}
        placeholder={label}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            input: {
              endAdornment: <Filters filters={filters} label="filters" />
            }
          }
        }}
      />
    </div>
  );
};

export default Search;
