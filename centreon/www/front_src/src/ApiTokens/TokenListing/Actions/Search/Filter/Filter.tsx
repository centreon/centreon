import { useState } from 'react';

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
    const data = value.map(({ creator }) => creator);
    setCreators(data);
  };

  const filterOptions = (options): Array<PersonalInformation> => {
    const data = options?.map(({ creator }) => creator);

    return data || [];
  };

  return (
    <div style={{ backgroundColor: 'white', minWidth: 600 }}>
      <MultiConnectedAutocompleteField
        disableSortedOptions
        className={classes.input}
        dataTestId={labelUser}
        field="name"
        getEndpoint={getEndpointConfiguredUser}
        label={t(labelUser)}
        value={users}
        onChange={changeUser}
      />

      <MultiConnectedAutocompleteField
        chipProps={{
          onDelete: (data, s) => console.log('delete', s, data)
        }}
        className={classes.input}
        dataTestId={labelCreator}
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
