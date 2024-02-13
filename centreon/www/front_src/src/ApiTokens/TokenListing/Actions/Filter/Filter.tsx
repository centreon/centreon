import { useState } from 'react';

import { useAtom } from 'jotai';
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
  labelCreator,
  labelSearch,
  labelUser
} from '../../../translatedLabels';
import { PersonalInformation } from '../../models';
import { getUniqData } from '../Search/utils';

import { usersAtom } from './atoms';
import { useStyles } from './filter.styles';
import { Fields } from './models';
import useBuildFilterValues from './useBuildFilterValues';

const Filter = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  // const [users, setUsers] = useState([]);
  const [creators, setCreators] = useState<Array<PersonalInformation>>([]);
  const [users, setUsers] = useAtom(usersAtom);

  useBuildFilterValues();

  const changeUser = (_, value): void => {
    setUsers(value);
  };

  const changeCreator = (_, value): void => {
    setCreators(value);
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
      : equals(option.name.toString(), selectedValue.name.toString());
  };

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        disableSortedOptions
        // allowUniqOption
        chipProps={{
          onDelete: deleteUser
        }}
        className={classes.input}
        dataTestId={labelUser}
        field="name"
        // filterOptions={(options) => {
        //   return getUniqData(options);
        // }}
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
        label={t(labelCreator)}
        value={creators}
        onChange={changeCreator}
      />
      <Button
        data-testid={labelSearch}
        labelSave={t(labelSearch)}
        startIcon={false}
        onClick={() => console.log('search')}
      />
    </div>
  );
};

export default Filter;
