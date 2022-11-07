<<<<<<< HEAD
import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';
=======
import * as React from 'react';

import { useTranslation } from 'react-i18next';
import { equals, isNil } from 'ramda';
>>>>>>> centreon/dev-21.10.x

import {
  PopoverMultiAutocompleteField,
  PopoverMultiConnectedAutocompleteField,
  SelectEntry,
  useMemoComponent,
} from '@centreon/ui';

<<<<<<< HEAD
import {
  filterWithParsedSearchDerivedAtom,
  setCriteriaAndNewFilterDerivedAtom,
} from '../filterAtoms';
=======
import { ResourceContext, useResourceContext } from '../../Context';
>>>>>>> centreon/dev-21.10.x

import { criteriaValueNameById, selectableCriterias } from './models';

interface Props {
  name: string;
  value: Array<SelectEntry>;
}

<<<<<<< HEAD
const CriteriaContent = ({ name, value }: Props): JSX.Element => {
  const { t } = useTranslation();

  const setCriteriaAndNewFilter = useUpdateAtom(
    setCriteriaAndNewFilterDerivedAtom,
  );

=======
const CriteriaContent = ({
  name,
  value,
  setCriteriaAndNewFilter,
}: Props & Pick<ResourceContext, 'setCriteriaAndNewFilter'>): JSX.Element => {
  const { t } = useTranslation();

>>>>>>> centreon/dev-21.10.x
  const getTranslated = (values: Array<SelectEntry>): Array<SelectEntry> => {
    return values.map((entry) => ({
      id: entry.id,
      name: t(entry.name),
    }));
  };

  const changeCriteria = (updatedValue): void => {
    setCriteriaAndNewFilter({ name, value: updatedValue });
  };

  const getUntranslated = (values): Array<SelectEntry> => {
    return values.map(({ id }) => ({
      id,
      name: criteriaValueNameById[id],
    }));
  };

  const { label, options, buildAutocompleteEndpoint, autocompleteSearch } =
    selectableCriterias[name];

  const commonProps = {
    label: t(label),
    search: autocompleteSearch,
  };

  if (isNil(options)) {
<<<<<<< HEAD
    const isOptionEqualToValue = (option, selectedValue): boolean =>
      isNil(option) ? false : equals(option.name, selectedValue.name);
=======
    const getOptionSelected = (option, selectedValue): boolean =>
      equals(option.name, selectedValue.name);
>>>>>>> centreon/dev-21.10.x

    const getEndpoint = ({ search, page }): string =>
      buildAutocompleteEndpoint({
        limit: 10,
        page,
        search,
      });

    return (
      <PopoverMultiConnectedAutocompleteField
        {...commonProps}
        disableSortedOptions
        field="name"
        getEndpoint={getEndpoint}
<<<<<<< HEAD
        isOptionEqualToValue={isOptionEqualToValue}
=======
        getOptionSelected={getOptionSelected}
>>>>>>> centreon/dev-21.10.x
        value={value}
        onChange={(_, updatedValue): void => {
          changeCriteria(updatedValue);
        }}
      />
    );
  }

  const translatedValues = getTranslated(value);
  const translatedOptions = getTranslated(options);

  return (
    <PopoverMultiAutocompleteField
      {...commonProps}
<<<<<<< HEAD
      hideInput
=======
>>>>>>> centreon/dev-21.10.x
      options={translatedOptions}
      value={translatedValues}
      onChange={(_, updatedValue): void => {
        changeCriteria(getUntranslated(updatedValue));
      }}
    />
  );
};

const Criteria = ({ value, name }: Props): JSX.Element => {
<<<<<<< HEAD
  const filterWithParsedSearch = useAtomValue(
    filterWithParsedSearchDerivedAtom,
  );

  return useMemoComponent({
    Component: <CriteriaContent name={name} value={value} />,
=======
  const { setCriteriaAndNewFilter, filterWithParsedSearch } =
    useResourceContext();

  return useMemoComponent({
    Component: (
      <CriteriaContent
        name={name}
        setCriteriaAndNewFilter={setCriteriaAndNewFilter}
        value={value}
      />
    ),
>>>>>>> centreon/dev-21.10.x
    memoProps: [value, name, filterWithParsedSearch],
  });
};

export default Criteria;
