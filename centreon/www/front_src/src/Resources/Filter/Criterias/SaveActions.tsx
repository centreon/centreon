import { useAtomValue } from 'jotai';
import { omit } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { createFilter, updateFilter } from '../api';
import { labelFilterCreated, labelFilterSaved } from '../../translatedLabels';
import { currentFilterAtom } from '../filterAtoms';
import CreateFilterDialog from '../Save/CreateFilterDialog';

import { Action } from './models';

const SaveActions = ({
  dataCreateFilter,
  dataUpdateFilter,
  loadFiltersAndUpdateCurrent
}): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const currentFilter = useAtomValue(currentFilterAtom);

  const { isCreateFilter, setIsCreateFilter } = dataCreateFilter;
  const { isUpdateFilter, setIsUpdateFilter } = dataUpdateFilter;

  const createFilterCallback = (result): void => {
    setIsCreateFilter(false);
    showSuccessMessage(t(labelFilterCreated));
    loadFiltersAndUpdateCurrent({ ...result });
  };

  const updateFilterCallback = (result): void => {
    showSuccessMessage(t(labelFilterSaved));
    loadFiltersAndUpdateCurrent(omit(['order'], result));
    setIsUpdateFilter(false);
  };

  const cancelCreateFilter = (): void => {
    setIsCreateFilter(false);
  };

  const cancelUpdateFilter = (): void => {
    setIsUpdateFilter(false);
  };

  return (
    <>
      {isCreateFilter && (
        <CreateFilterDialog
          callbackSuccess={createFilterCallback}
          open={isCreateFilter}
          payloadAction={{ criterias: currentFilter?.criterias }}
          request={createFilter}
          onCancel={cancelCreateFilter}
        />
      )}

      {isUpdateFilter && (
        <CreateFilterDialog
          action={Action.update}
          callbackSuccess={updateFilterCallback}
          open={isUpdateFilter}
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
