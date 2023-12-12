import { Dispatch, SetStateAction } from 'react';

import { useAtomValue } from 'jotai';
import { omit } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { createFilter, updateFilter } from '../api';
import { labelFilterCreated, labelFilterSaved } from '../../translatedLabels';
import { currentFilterAtom } from '../filterAtoms';
import CreateFilterDialog from '../Save/CreateFilterDialog';
import { Filter } from '../models';

import { Action } from './models';

interface DataCreateFilter {
  isCreatingFilter: boolean;
  setIsCreatingFilter: Dispatch<SetStateAction<boolean>>;
}

interface DataUpdateFilter {
  isUpdatingFilter: boolean;
  setIsUpdatingFilter: Dispatch<SetStateAction<boolean>>;
}

interface Props {
  dataCreateFilter: DataCreateFilter;
  dataUpdateFilter: DataUpdateFilter;
  loadFiltersAndUpdateCurrent: (data: Filter) => void;
}

const SaveActions = ({
  dataCreateFilter,
  dataUpdateFilter,
  loadFiltersAndUpdateCurrent
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const currentFilter = useAtomValue(currentFilterAtom);

  const { isCreatingFilter, setIsCreatingFilter } = dataCreateFilter;
  const { isUpdatingFilter, setIsUpdatingFilter } = dataUpdateFilter;

  const createFilterCallback = (result): void => {
    setIsCreatingFilter(false);
    showSuccessMessage(t(labelFilterCreated));
    loadFiltersAndUpdateCurrent({ ...result });
  };

  const updateFilterCallback = (result): void => {
    showSuccessMessage(t(labelFilterSaved));
    loadFiltersAndUpdateCurrent(omit(['order'], result));
    setIsUpdatingFilter(false);
  };

  const cancelCreateFilter = (): void => {
    setIsCreatingFilter(false);
  };

  const cancelUpdateFilter = (): void => {
    setIsUpdatingFilter(false);
  };

  return (
    <>
      {isCreatingFilter && (
        <CreateFilterDialog
          callbackSuccess={createFilterCallback}
          open={isCreatingFilter}
          payloadAction={{ criterias: currentFilter?.criterias }}
          request={createFilter}
          onCancel={cancelCreateFilter}
        />
      )}

      {isUpdatingFilter && (
        <CreateFilterDialog
          action={Action.update}
          callbackSuccess={updateFilterCallback}
          open={isUpdatingFilter}
          payloadAction={{
            filter: omit(['id'], currentFilter),
            id: currentFilter.id
          }}
          request={updateFilter}
          onCancel={cancelUpdateFilter}
        />
      )}
    </>
  );
};

export default SaveActions;
