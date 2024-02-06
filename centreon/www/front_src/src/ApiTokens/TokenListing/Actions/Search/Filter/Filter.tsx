import { useState } from 'react';

import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { MultiConnectedAutocompleteField } from '@centreon/ui';

import { labelCreator, labelUser } from '../../../../translatedLabels';
import {
  getEndpointConfiguredUser,
  getEndpointCreatorsToken
} from '../../../../api/endpoints';
import { PersonalInformation } from '../../../models';

import { useStyles } from './filter.styles';

const Filter = (): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();
  const [users, setUsers] = useState([]);
  const [creators, setCreators] = useState<Array<PersonalInformation>>([]);

  const changeUser = (_, value): void => {
    setUsers(value);
  };

  const changeCreator = (_, value): void => {
    setCreators(value);
  };

  const getUniqData = (data): Array<PersonalInformation> => {
    const result = [
      ...new Map(data.map((item) => [item.id, item])).values()
    ] as Array<PersonalInformation>;

    return result || [];
  };

  const filterOptions = (options): Array<PersonalInformation> => {
    const creatorsData = options?.map(({ creator }) => creator);

    return getUniqData(creatorsData);
  };

  const deleteCreator = (_, item): void => {
    const data = creators.filter(({ id }) => !equals(item.id, id));
    setCreators(data);
  };

  const deleteUser = (_, item): void => {
    const data = users.filter(({ id }) => !equals(item.id, id));
    setUsers(data);
  };

  return (
    <div className={classes.container}>
      <MultiConnectedAutocompleteField
        disableSortedOptions
        chipProps={{
          onDelete: deleteUser
        }}
        className={classes.input}
        dataTestId={labelUser}
        field="name"
        getEndpoint={getEndpointConfiguredUser}
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
        filterOptions={filterOptions}
        getEndpoint={getEndpointCreatorsToken}
        label={t(labelCreator)}
        value={creators}
        onChange={changeCreator}
      />
    </div>
  );
};

export default Filter;
