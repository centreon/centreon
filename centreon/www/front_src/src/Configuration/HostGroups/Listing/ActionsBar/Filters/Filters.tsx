import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { TextField } from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import {
  labelAlias,
  labelClear,
  labelDisabled,
  labelEnabled,
  labelName,
  labelSearch,
  labelStatus
} from '../../../translatedLabels';
import { filtersAtom } from '../../atom';
import useLoadData from '../../useLoadData';
import { useFilterStyles } from './Filters.styles';

import {
  Checkbox,
  FormControlLabel,
  FormGroup,
  Typography
} from '@mui/material';
import { filtersDefaultValue } from '../../utils';

const Filters = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useFilterStyles();

  const [filters, setFilters] = useAtom(filtersAtom);
  const { reload, isLoading } = useLoadData();

  const change =
    (key) =>
    (event): void => {
      setFilters({ ...filters, [key]: event.target.value });
    };

  const changeCheckbox =
    (key) =>
    (event): void => {
      setFilters({ ...filters, [key]: event.target.checked });
    };

  const isClearDisabled = equals(filters, filtersDefaultValue);

  const reset = (): void => {
    setFilters(filtersDefaultValue);
    reload();
  };

  return (
    <div className={classes.additionalFilters} data-testid="filters">
      <TextField
        fullWidth
        dataTestId={labelName}
        label={t(labelName)}
        value={filters.name}
        onChange={change('name')}
      />

      <TextField
        fullWidth
        dataTestId={labelAlias}
        label={t(labelAlias)}
        value={filters.alias}
        onChange={change('alias')}
      />
      <div className={classes.statusFilter}>
        <Typography className={classes.statusFilterName}>
          {t(labelStatus)}
        </Typography>
        <FormGroup row>
          <FormControlLabel
            control={
              <Checkbox
                checked={filters.enabled}
                name={t(labelEnabled)}
                onChange={changeCheckbox('enabled')}
              />
            }
            label={t(labelEnabled)}
          />
          <FormControlLabel
            control={
              <Checkbox
                checked={filters.disabled}
                name={t(labelDisabled)}
                onChange={changeCheckbox('disabled')}
              />
            }
            label={t(labelDisabled)}
          />
        </FormGroup>
      </div>
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
