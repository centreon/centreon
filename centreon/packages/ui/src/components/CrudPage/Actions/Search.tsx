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
        debounced
        fullWidth
        dataTestId={label}
        placeholder={label}
        onChange={change}
        textFieldSlotsAndSlotProps={{
          slotProps: {
            input: {
              endAdornment: <Filters label="filters" filters={filters} />
            }
          }
        }}
      />
    </div>
  );
};

export default Search;
