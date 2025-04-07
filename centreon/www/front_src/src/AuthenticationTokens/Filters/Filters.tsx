import { useTranslation } from 'react-i18next';

import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import Status from './Status';

import { CreationDate, ExpirationDate } from './DateInput';

import useLoadData from '../Listing/useLoadData';
import { tokenTypes } from '../Modal/utils';

import {
  getEndpointConfiguredUser,
  getEndpointCreatorsToken
} from '../api/endpoints';
import { useStyles } from './Filters.styles';
import useFilters from './useFilters';

import {
  labelClear,
  labelCreators,
  labelName,
  labelSearch,
  labelTypes,
  labelUsers
} from '../translatedLabels';

const Filters = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { isLoading } = useLoadData();

  const {
    filters,
    isClearDisabled,
    changeName,
    changeTypes,
    changeUser,
    changeCreator,
    filterCreators,
    deleteCreator,
    deleteUser,
    deleteType,
    isOptionEqualToValue,
    reload,
    reset
  } = useFilters();

  return (
    <div className={classes.container} data-testid="FilterContainer">
      <TextField
        fullWidth
        dataTestId={labelName}
        label={t(labelName)}
        value={filters.name}
        onChange={changeName}
      />

      <MultiAutocompleteField
        chipProps={{
          onDelete: deleteType,
          color: 'primary'
        }}
        dataTestId={labelTypes}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelTypes)}
        options={tokenTypes}
        value={filters.types}
        onChange={changeTypes}
      />

      <CreationDate />
      <ExpirationDate />

      <MultiConnectedAutocompleteField
        disableClearable={false}
        disableSortedOptions
        chipProps={{
          onDelete: deleteUser,
          color: 'primary'
        }}
        dataTestId={labelUsers}
        field="name"
        getEndpoint={getEndpointConfiguredUser}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelUsers)}
        value={filters.users}
        onChange={changeUser}
      />

      <MultiConnectedAutocompleteField
        disableClearable={false}
        disableSortedOptions
        chipProps={{
          onDelete: deleteCreator,
          color: 'primary'
        }}
        dataTestId={labelCreators}
        field="creator.name"
        filterOptions={filterCreators}
        getEndpoint={getEndpointCreatorsToken}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelCreators)}
        value={filters.creators}
        onChange={changeCreator}
      />
      <Status />

      <div className={classes.additionalFiltersButtons}>
        <Button
          data-testid={labelClear}
          disabled={isClearDisabled}
          size="small"
          variant="ghost"
          onClick={reset}
        >
          {t(labelClear)}
        </Button>
        <Button
          data-testid={labelSearch}
          disabled={isLoading}
          size="small"
          onClick={reload}
        >
          {t(labelSearch)}
        </Button>
      </div>
    </div>
  );
};

export default Filters;
