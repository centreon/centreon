import { useAtomValue } from 'jotai';
import { equals, isEmpty, omit, propEq, reject } from 'ramda';
import { useTranslation } from 'react-i18next';

import EditIcon from '@mui/icons-material/Edit';
import SaveIcon from '@mui/icons-material/Save';
import Button from '@mui/material/Button';

import { Criteria } from '../../Criterias/models';
import useActionFilter from '../../Edit/EditButton/useActionFilter';
import { currentFilterAtom, customFiltersAtom } from '../../filterAtoms';
import {
  allFilter,
  resourceProblemsFilter,
  unhandledProblemsFilter
} from '../../models';
import { labelSaveAs, labelUpdate } from '../translatedLabels';

interface Save {
  canSaveFilter: boolean;
  canSaveFilterAsNew: boolean;
  closePopover?: () => void;
  getIsCreateFilter: (value: boolean) => void;
}

const getSelectableCriterias = (
  criterias: Array<Criteria>
): Array<Criteria> => {
  const filteredCriterias = reject<Criteria>(propEq('name', 'sort'))(criterias);

  return filteredCriterias.map(omit(['search_data']));
};

const Save = ({
  canSaveFilterAsNew,
  canSaveFilter,
  getIsCreateFilter,
  closePopover
}: Save): JSX.Element => {
  const { t } = useTranslation();

  const currentFilter = useAtomValue(currentFilterAtom);
  const customFilters = useAtomValue(customFiltersAtom);
  const { updateFilter } = useActionFilter();

  const baseFilters = [
    unhandledProblemsFilter,
    resourceProblemsFilter,
    allFilter
  ];

  const selectableFilters = [...baseFilters, ...customFilters];

  const isNewFilter = isEmpty(currentFilter.id);

  const selectedCustomFilter = isNewFilter
    ? null
    : selectableFilters.find(propEq('id', currentFilter.id));

  const saveButtonDisabled =
    !isNewFilter &&
    equals(
      getSelectableCriterias(currentFilter.criterias),
      getSelectableCriterias(selectedCustomFilter?.criterias || [])
    );

  const saveAsNew = (): void => {
    getIsCreateFilter(true);
    closePopover?.();
  };

  const saveAs = (): void => {
    updateFilter();
    closePopover?.();
  };

  return (
    <>
      <Button
        disabled={saveButtonDisabled || !canSaveFilterAsNew}
        startIcon={<SaveIcon fontSize="small" />}
        variant="outlined"
        onClick={saveAsNew}
      >
        {t(labelSaveAs)}
      </Button>
      <Button
        disabled={saveButtonDisabled || !canSaveFilter}
        startIcon={<EditIcon fontSize="small" />}
        variant="outlined"
        onClick={saveAs}
      >
        {t(labelUpdate)}
      </Button>
    </>
  );
};

export default Save;
