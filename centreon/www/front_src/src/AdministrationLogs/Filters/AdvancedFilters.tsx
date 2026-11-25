import type { ChipProps } from '@mui/material';

import {
  MultiAutocompleteField,
  type SelectEntry,
  useFetchQuery
} from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import { useAtom, useSetAtom } from 'jotai';
import { isEmpty, pick, reject } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';

import { actorsDecoder, tokensDecoder } from '../api/decoders';
import { actorsEndpoint, tokensEndpoint } from '../api/endpoints';
import { filtersAtom, pageAtom } from '../atoms';
import { Action, type Filters } from '../models';
import {
  labelAction,
  labelApiToken,
  labelClear,
  labelObjectType,
  labelSearch,
  labelTargetTypes,
  labelUser
} from '../translatedLabels';
import { actionLabels } from '../utils';

type MultiFilterKey = 'actions' | 'actors' | 'targetTypes' | 'tokens';

type AdvancedFiltersValues = Pick<Filters, MultiFilterKey>;

const multiFilterKeys: Array<MultiFilterKey> = [
  'actors',
  'tokens',
  'targetTypes',
  'actions'
];

const emptyAdvancedFilters: AdvancedFiltersValues = {
  actions: [],
  actors: [],
  targetTypes: [],
  tokens: []
};

interface Props {
  close?: () => void;
}

// Same behaviour as the advanced filters of the configuration listings (Hosts):
// the fields are edited locally and applied with the Search button
const AdvancedFilters = ({ close }: Props): JSX.Element => {
  const { t } = useTranslation();

  const [filters, setFilters] = useAtom(filtersAtom);
  const setPage = useSetAtom(pageAtom);

  const [values, setValues] = useState<AdvancedFiltersValues>(
    pick(multiFilterKeys, filters)
  );

  const { data: actors } = useFetchQuery({
    decoder: actorsDecoder,
    getEndpoint: () => actorsEndpoint,
    getQueryKey: () => ['activity-log-actors'],
    queryOptions: { suspense: false }
  });

  const actorOptions: Array<SelectEntry> = (actors?.result ?? []).map(
    ({ id, name }) => ({ id, name })
  );

  const { data: tokens } = useFetchQuery({
    decoder: tokensDecoder,
    getEndpoint: () => tokensEndpoint,
    getQueryKey: () => ['activity-log-tokens'],
    queryOptions: { suspense: false }
  });

  // The name is the id: the filter matches on it
  const tokenOptions: Array<SelectEntry> = (tokens?.result ?? []).map(
    ({ name }) => ({ id: name, name })
  );

  const targetTypeOptions: Array<SelectEntry> = Object.entries(
    labelTargetTypes
  ).map(([id, name]) => ({ id, name: t(name) }));

  const actionOptions: Array<SelectEntry> = Object.values(Action).map(
    (action) => ({ id: action, name: t(actionLabels[action]) })
  );

  const apply = (newValues: AdvancedFiltersValues): void => {
    setPage(1);
    setFilters((current) => ({ ...current, ...newValues }));
  };

  const changeMulti =
    (key: MultiFilterKey) =>
    (_event: unknown, selected: unknown): void =>
      setValues((current) => ({
        ...current,
        [key]: selected as Array<SelectEntry>
      }));

  // MultiAutocompleteField calls onDelete with the deleted option as second
  // argument, which the MUI chip typing does not know about
  const getChipProps = (key: MultiFilterKey): ChipProps => ({
    color: 'primary',
    onDelete: ((_event: unknown, option: SelectEntry): void =>
      setValues((current) => ({
        ...current,
        [key]: reject(({ id }) => id === option.id, current[key])
      }))) as ChipProps['onDelete']
  });

  const isClearDisabled = multiFilterKeys.every(
    (key) => isEmpty(values[key]) && isEmpty(filters[key])
  );

  const clear = (): void => {
    setValues(emptyAdvancedFilters);
    apply(emptyAdvancedFilters);
  };

  const search = (): void => {
    apply(values);
    close?.();
  };

  return (
    <div
      className="mt-2 flex w-[480px] max-w-[calc(100vw-32px)] flex-col gap-3 bg-background-paper p-4"
      data-testid="advanced-filters"
    >
      <MultiAutocompleteField
        chipProps={getChipProps('actors')}
        dataTestId={labelUser}
        label={t(labelUser)}
        onChange={changeMulti('actors')}
        options={actorOptions}
        value={values.actors}
      />
      <MultiAutocompleteField
        chipProps={getChipProps('tokens')}
        dataTestId={labelApiToken}
        label={t(labelApiToken)}
        onChange={changeMulti('tokens')}
        options={tokenOptions}
        value={values.tokens}
      />
      <MultiAutocompleteField
        chipProps={getChipProps('targetTypes')}
        dataTestId={labelObjectType}
        label={t(labelObjectType)}
        onChange={changeMulti('targetTypes')}
        options={targetTypeOptions}
        value={values.targetTypes}
      />
      <MultiAutocompleteField
        chipProps={getChipProps('actions')}
        dataTestId={labelAction}
        disableSortedOptions
        label={t(labelAction)}
        onChange={changeMulti('actions')}
        options={actionOptions}
        value={values.actions}
      />
      <div className="mt-4 flex justify-between">
        <Button
          data-testid={labelClear}
          disabled={isClearDisabled}
          onClick={clear}
          size="small"
          variant="ghost"
        >
          {t(labelClear)}
        </Button>
        <Button data-testid={labelSearch} onClick={search} size="small">
          {t(labelSearch)}
        </Button>
      </div>
    </div>
  );
};

export default AdvancedFilters;
