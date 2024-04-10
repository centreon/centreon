import { ChangeEvent } from 'react';

import { useAtom } from 'jotai';

import { ResourceTypeEnum } from '../../../models';
import { isAllOfResourceTypeCheckedAtom } from '../../../atom';
import {
  labelAllHostGroups,
  labelAllHosts,
  labelAllServiceGroups
} from '../../../translatedLabels';

interface UseAllOfResourceTypeCheckboxState {
  checkboxLabel: string;
  checked: boolean;
  onChange: (event: ChangeEvent<HTMLInputElement>) => void;
}

const allOfResourceTypeLabels = {
  [ResourceTypeEnum.HostGroup]: labelAllHostGroups,
  [ResourceTypeEnum.Host]: labelAllHosts,
  [ResourceTypeEnum.ServiceGroup]: labelAllServiceGroups
};

export const useAllOfResourceTypeCheckbox = (
  resourceType: ResourceTypeEnum
): UseAllOfResourceTypeCheckboxState => {
  const [isAllOfResourceTypeChecked, setIsAllOfResourceTypeChecked] = useAtom(
    isAllOfResourceTypeCheckedAtom
  );

  const checkboxLabel = allOfResourceTypeLabels[resourceType];

  const onChange = (event: ChangeEvent<HTMLInputElement>): void =>
    setIsAllOfResourceTypeChecked({
      ...isAllOfResourceTypeChecked,
      [resourceType]: event.target.checked
    });

  return {
    checkboxLabel,
    checked: isAllOfResourceTypeChecked[resourceType],
    onChange
  };
};
