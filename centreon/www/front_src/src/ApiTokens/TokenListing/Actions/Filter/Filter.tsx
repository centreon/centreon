import { useAtom, useSetAtom } from 'jotai';
import { equals, isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  SaveButton as Button,
  MultiConnectedAutocompleteField
} from '@centreon/ui';

import {
  getEndpointConfiguredUser,
  getEndpointCreatorsToken
} from '../../../api/endpoints';
import {
  labelClear,
  labelCreator,
  labelSearch,
  labelUser
} from '../../../translatedLabels';
import { PersonalInformation } from '../../models';
import useBuildParameters from '../Search/useBuildParametrs';
import { getUniqData } from '../Search/utils';

import DateInputWrapper from './DateInput';
import Status from './Status';
import {
  creatorsAtom,
  currentFilterAtom,
  customQueryParametersAtom,
  usersAtom
} from './atoms';
import { useStyles } from './filter.styles';
import { Fields } from './models';
import useBuildFilterValues from './useBuildFilterValues';
import useInitializeFilter from './useInitializeFilter';

const Filter = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const [creators, setCreators] = useAtom(creatorsAtom);
  const [users, setUsers] = useAtom(usersAtom);
  const [currentFilter, setCurrentFilter] = useAtom(currentFilterAtom);
  const setCustomQueryParameters = useSetAtom(customQueryParametersAtom);
  const { queryParameters, getSearchParameters } = useBuildParameters();

  const { initialize } = useInitializeFilter();

  useBuildFilterValues();

  const wordToRegex = (input: string): string => {
    return input.replace(/\s/g, '\\s+');
  };

  const changeUser = (_, value): void => {
    const formattedValues = value.map((item) => ({
      ...item,
      name: wordToRegex(item.name)
    }));
    setUsers(formattedValues);
  };

  const changeCreator = (_, value): void => {
    const formattedValues = value.map((item) => ({
      ...item,
      name: wordToRegex(item.name)
    }));
    setCreators(formattedValues);
  };

  const filterCreators = (options): Array<PersonalInformation> => {
    const creatorsData = options?.map(({ creator }) => creator);

    return getUniqData(creatorsData);
  };

  const deleteCreator = (_, item): void => {
    const data = creators.filter(({ name }) => !equals(item.name, name));
    setCreators(data);
  };

  const deleteUser = (_, item): void => {
    const data = users.filter(({ name }) => !equals(item.name, name));
    setUsers(data);
  };

  const isOptionEqualToValue = (option, selectedValue): boolean => {
    return isNil(option)
      ? false
      : equals(
          wordToRegex(option.name).toString(),
          selectedValue.name.toString()
        );
  };

  const handleSearch = (): void => {
    setCurrentFilter({ ...currentFilter, search: getSearchParameters() });
    setCustomQueryParameters(queryParameters);
  };

  return (
    <div className={classes.container}>
      <DateInputWrapper />

      <MultiConnectedAutocompleteField
        disableSortedOptions
        chipProps={{
          onDelete: deleteUser
        }}
        className={classes.input}
        dataTestId={labelUser}
        field="name"
        getEndpoint={getEndpointConfiguredUser}
        id={Fields.UserName}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelUser)}
        value={users}
        onChange={changeUser}
      />

      <MultiConnectedAutocompleteField
        disableSortedOptions
        chipProps={{
          onDelete: deleteCreator
        }}
        className={classes.input}
        dataTestId={labelCreator}
        field="name"
        filterOptions={filterCreators}
        getEndpoint={getEndpointCreatorsToken}
        id={Fields.CreatorName}
        isOptionEqualToValue={isOptionEqualToValue}
        label={t(labelCreator)}
        value={creators}
        onChange={changeCreator}
      />
      <Status />

      <div className={classes.buttonsContainer}>
        <Button
          data-testid={labelClear}
          labelSave={t(labelClear)}
          startIcon={false}
          variant="text"
          onClick={initialize}
        />
        <Button
          data-testid={labelSearch}
          labelSave={t(labelSearch)}
          startIcon={false}
          onClick={handleSearch}
        />
      </div>
    </div>
  );
};

export default Filter;
