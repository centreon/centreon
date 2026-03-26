import { useFormikContext } from 'formik';
import { equals, isEmpty } from 'ramda';
import { useCallback, useMemo } from 'react';

import { labelPleaseSelectAResource } from '../../../../translatedLabels';
import { Resource } from '../../../../Widgets/models';
import {
  DefaultResourceType,
  SelectType,
  Widget,
  WidgetDataResource,
  WidgetResourceType
} from '../../../models';

interface UseDefaultSelectTypeData {
  selectType?: SelectType;
  value?: Array<WidgetDataResource>;
}

const useDefaultSelectTypeData = ({
  selectType,
  value
}: UseDefaultSelectTypeData) => {
  const { errors, setErrors } = useFormikContext<Widget>();

  const getDefaultSelectType = useCallback(
    (
      currentResourceType: WidgetResourceType
    ): DefaultResourceType | undefined => {
      return selectType?.defaultResourceType.find(({ resourceType }) =>
        equals(resourceType, currentResourceType)
      );
    },
    []
  );

  const getDefaultRequiredSelectType = useCallback(
    (currentResourceType: WidgetResourceType) => {
      const defaultSelectedType = getDefaultSelectType(currentResourceType);
      return Boolean(defaultSelectedType?.requied);
    },
    []
  );

  const getDefaultDisabledSelectType = useCallback(
    (currentResourceType: WidgetResourceType) => {
      const defaultSelectedType = getDefaultSelectType(currentResourceType);
      const { when, matches } = defaultSelectedType?.disabled || {};
      const targetValue = value?.find(
        (item) =>
          item[when as keyof Resource] &&
          equals(item[when as keyof Resource], matches)
      );

      return targetValue?.resources?.length <= 0;
    },
    [value]
  );

  const defaultSelectTypeErrors = useMemo(
    () =>
      selectType?.defaultResourceType
        .flatMap((item) => {
          return value?.flatMap(({ resourceType, resources }) => {
            if (
              equals(item?.resourceType, resourceType) &&
              item?.requied &&
              isEmpty(resources)
            ) {
              return { resources: labelPleaseSelectAResource, resourceType };
            }
            return null;
          });
        })
        .filter((item) => item),
    [value]
  );

  const validateDefaultSelectTypeData = useCallback(() => {
    if (isEmpty(defaultSelectTypeErrors) || !defaultSelectTypeErrors) {
      return;
    }

    const currentErrors =
      'data' in errors
        ? {
            ...errors,
            data:
              'resources' in [errors.data]
                ? {
                    resources: [
                      ...errors.data.resources,
                      ...defaultSelectTypeErrors
                    ]
                  }
                : { ...errors.data, resources: defaultSelectTypeErrors }
          }
        : { data: { resources: defaultSelectTypeErrors } };

    setErrors({ ...errors, ...currentErrors });
  }, [defaultSelectTypeErrors]);

  validateDefaultSelectTypeData();

  return {
    getDefaultDisabledSelectType,
    getDefaultRequiredSelectType
  };
};

export default useDefaultSelectTypeData;
