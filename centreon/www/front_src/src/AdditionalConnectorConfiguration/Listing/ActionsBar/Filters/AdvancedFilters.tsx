import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField, TextField } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { getPollersEndpoint, getConnectorTypesEndpoint } from '../../api';
import {
  labelClear,
  labelName,
  labelPollers,
  labelSearch,
  labelType
} from '../../../translatedLabels';
import { useFilterStyles } from '../useActionsStyles';

const AdvancedFilters = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const reset = (): void => undefined;
  const handleSearch = (): void => undefined;

  return (
    <div className={classes.additionalFilters} data-testid="FilterContainer">
      <TextField
        fullWidth
        dataTestId={labelName}
        placeholder={t(labelName)}
        value={undefined}
        onChange={() => undefined}
      />
      <MultiConnectedAutocompleteField
        chipProps={{
          onDelete: () => undefined
        }}
        dataTestId={labelType}
        field="name"
        getEndpoint={getPollersEndpoint}
        // id={Fields.UserName}
        // isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelType)}
        value={undefined}
        onChange={() => undefined}
      />

      <MultiConnectedAutocompleteField
        disableSortedOptions
        chipProps={{
          onDelete: () => undefined
        }}
        dataTestId={labelPollers}
        // field="name"
        getEndpoint={getConnectorTypesEndpoint}
        // id={Fields.CreatorName}
        // isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelPollers)}
        value={undefined}
        onChange={() => undefined}
      />

      <div className={classes.additionalFiltersButtons}>
        <Button
          data-testid={labelClear}
          size="small"
          variant="ghost"
          onClick={reset}
        >
          {t(labelClear)}
        </Button>
        <Button data-testid={labelSearch} size="small" onClick={handleSearch}>
          {t(labelSearch)}
        </Button>
      </div>
    </div>
  );
};

export default AdvancedFilters;
