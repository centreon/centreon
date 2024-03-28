import { Dispatch, SetStateAction } from 'react';

import { useAtomValue } from 'jotai';
import { omit } from 'ramda';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { labelFilterCreated } from '../../translatedLabels';
import useActionFilter from '../Edit/EditButton/useActionFilter';
import CreateFilterDialog from '../Save/CreateFilterDialog';
import { createFilter } from '../api';
import { currentFilterAtom } from '../filterAtoms';

interface DataCreateFilter {
  isCreatingFilter: boolean;
  setIsCreatingFilter: Dispatch<SetStateAction<boolean>>;
}

interface Props {
  dataCreateFilter: DataCreateFilter;
}

const SaveActions = ({ dataCreateFilter }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const currentFilter = useAtomValue(currentFilterAtom);
  const { loadFiltersAndUpdateCurrent } = useActionFilter();

  const { isCreatingFilter, setIsCreatingFilter } = dataCreateFilter;

  const createFilterCallback = (result): void => {
    setIsCreatingFilter(false);
    showSuccessMessage(t(labelFilterCreated));

    loadFiltersAndUpdateCurrent(omit(['order'], result));
  };

  const cancelCreateFilter = (): void => {
    setIsCreatingFilter(false);
  };

  return (
    <div>
      {isCreatingFilter && (
        <CreateFilterDialog
          callbackSuccess={createFilterCallback}
          open={isCreatingFilter}
          payloadAction={{ criterias: currentFilter?.criterias }}
          request={createFilter}
          onCancel={cancelCreateFilter}
        />
      )}
    </div>
  );
};

export default SaveActions;
