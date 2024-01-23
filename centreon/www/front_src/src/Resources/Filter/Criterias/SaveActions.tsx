import { Dispatch, SetStateAction } from 'react';

import { useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';

import { useSnackbar } from '@centreon/ui';

import { labelFilterCreated } from '../../translatedLabels';
import CreateFilterDialog from '../Save/CreateFilterDialog';
import { createFilter } from '../api';
import { currentFilterAtom } from '../filterAtoms';
import { Filter } from '../models';

interface DataCreateFilter {
  isCreatingFilter: boolean;
  setIsCreatingFilter: Dispatch<SetStateAction<boolean>>;
}

interface Props {
  dataCreateFilter: DataCreateFilter;
  loadFiltersAndUpdateCurrent: (data: Filter) => void;
}

const SaveActions = ({
  dataCreateFilter,
  loadFiltersAndUpdateCurrent
}: Props): JSX.Element => {
  const { t } = useTranslation();
  const { showSuccessMessage } = useSnackbar();

  const currentFilter = useAtomValue(currentFilterAtom);

  const { isCreatingFilter, setIsCreatingFilter } = dataCreateFilter;

  const createFilterCallback = (result): void => {
    setIsCreatingFilter(false);
    showSuccessMessage(t(labelFilterCreated));
    loadFiltersAndUpdateCurrent({ ...result });
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
