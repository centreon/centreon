import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField, TextField } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import {
  getEndpointConfiguredUser,
  getEndpointCreatorsToken
} from '../../../api/endpoints';
import {
  labelClear,
  labelCreator,
  labelName,
  labelSearch,
  labelUser
} from '../../../translatedLabels';
import useLoadData from '../../useLoadData';

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
    changeUser,
    changeCreator,
    filterCreators,
    deleteCreator,
    deleteUser,
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
        optionProperty="alias"
        value={filters.users}
        onChange={changeUser}
      />

      <MultiConnectedAutocompleteField
        disableSortedOptions
        chipProps={{
          color: 'primary',
          onDelete: deleteCreator
        }}
        dataTestId={labelCreator}
        disableClearable={false}
        field="creator.name"
        filterOptions={filterCreators}
        getEndpoint={getEndpointCreatorsToken}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelCreator)}
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
