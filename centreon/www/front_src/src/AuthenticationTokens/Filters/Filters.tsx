import {
  MultiAutocompleteField,
  MultiConnectedAutocompleteField,
  TextField
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { useTranslation } from 'react-i18next';

import {
  getEndpointConfiguredUser,
  getEndpointCreatorsToken
} from '../api/endpoints';
import useLoadData from '../Listing/useLoadData';
import { tokenTypes } from '../Modal/utils';
import {
  labelClear,
  labelCreator,
  labelName,
  labelSearch,
  labelType,
  labelUser
} from '../translatedLabels';
import { CreationDate, ExpirationDate } from './DateInput';
import { useStyles } from './Filters.styles';
import Status from './Status';
import useFilters from './useFilters';

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
        dataTestId={labelName}
        fullWidth
        label={t(labelName)}
        onChange={changeName}
        value={filters.name}
      />

      <MultiAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deleteType
        }}
        dataTestId={labelType}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelType)}
        onChange={changeTypes}
        options={tokenTypes}
        value={filters.types}
      />

      <CreationDate />
      <ExpirationDate />

      <MultiConnectedAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deleteUser
        }}
        dataTestId={labelUser}
        disableClearable={false}
        field="alias"
        getEndpoint={getEndpointConfiguredUser}
        getRenderedOptionText={(option): string => option?.alias?.toString()}
        label={t(labelUser)}
        onChange={changeUser}
        optionProperty="alias"
        value={filters.users}
      />

      <MultiConnectedAutocompleteField
        chipProps={{
          color: 'primary',
          onDelete: deleteCreator
        }}
        dataTestId={labelCreator}
        disableClearable={false}
        disableSortedOptions
        field="creator.name"
        filterOptions={filterCreators}
        getEndpoint={getEndpointCreatorsToken}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelCreator)}
        onChange={changeCreator}
        value={filters.creators}
      />
      <Status />

      <div className={classes.additionalFiltersButtons}>
        <Button
          data-testid={labelClear}
          disabled={isClearDisabled}
          onClick={reset}
          size="small"
          variant="ghost"
        >
          {t(labelClear)}
        </Button>
        <Button
          data-testid={labelSearch}
          disabled={isLoading}
          onClick={reload}
          size="small"
        >
          {t(labelSearch)}
        </Button>
      </div>
    </div>
  );
};

export default Filters;
