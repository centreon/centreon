import { ChangeEvent, useEffect, useMemo } from 'react';

import { useFormikContext } from 'formik';
import {
  T,
  always,
  cond,
  equals,
  isEmpty,
  propEq,
  reject,
  pluck,
  includes,
  isNotNil,
  last,
  filter
} from 'ramda';
import { useAtomValue } from 'jotai';

import {
  QueryParameter,
  SelectEntry,
  buildListingEndpoint
} from '@centreon/ui';

import {
  Widget,
  WidgetDataResource,
  WidgetPropertyProps,
  WidgetResourceType
} from '../../../models';
import {
  labelHost,
  labelHostCategory,
  labelHostGroup,
  labelPleaseSelectAResource,
  labelService,
  labelServiceCategory,
  labelServiceGroup
} from '../../../../translatedLabels';
import { baseEndpoint } from '../../../../../../../api/endpoint';
import { getDataProperty } from '../utils';
import {
  hasMetricInputTypeDerivedAtom,
  widgetPropertiesMetaPropertiesDerivedAtom
} from '../../../atoms';

interface UseResourcesState {
  addButtonHidden?: boolean;
  addResource: () => void;
  changeIdValue: (resourceType) => (({ name }) => string) | undefined;
  changeResource: (index: number) => (_, resources: SelectEntry) => void;
  changeResourceType: (
    index: number
  ) => (e: ChangeEvent<HTMLInputElement>) => void;
  changeResources: (
    index: number
  ) => (_, resources: Array<SelectEntry>) => void;
  deleteResource: (index: number) => () => void;
  deleteResourceItem: ({ index, option, resources }) => void;
  error: string | null;
  getResourceResourceBaseEndpoint: ({
    index,
    resourceType
  }: {
    index: number;
    resourceType: string;
  }) => (parameters) => string;
  getResourceStatic: (resourceType: WidgetResourceType) => boolean | undefined;
  getResourceTypeOptions: (index, resource) => Array<ResourceTypeOption>;
  getSearchField: (resourceType: WidgetResourceType) => string;
  isLastResourceInTree: boolean;
  singleHostPerMetric?: boolean;
  singleMetricSelection?: boolean;
  value: Array<WidgetDataResource>;
}

interface ResourceTypeOption {
  id: WidgetResourceType;
  name: string;
}

export const resourceTypeBaseEndpoints = {
  [WidgetResourceType.host]: '/resources',
  [WidgetResourceType.hostCategory]: '/hosts/categories',
  [WidgetResourceType.hostGroup]: '/hostgroups',
  [WidgetResourceType.service]: '/services/names',
  [WidgetResourceType.serviceCategory]: '/services/categories',
  [WidgetResourceType.serviceGroup]: '/servicegroups'
};

export const resourceTypeOptions = [
  {
    availableResourceTypeOptions: [
      { id: WidgetResourceType.serviceGroup, name: labelServiceGroup },
      { id: WidgetResourceType.serviceCategory, name: labelServiceCategory },
      { id: WidgetResourceType.service, name: labelService }
    ],
    id: WidgetResourceType.host,
    name: labelHost
  },
  {
    availableResourceTypeOptions: [
      { id: WidgetResourceType.hostGroup, name: labelHostGroup },
      { id: WidgetResourceType.host, name: labelHost },
      { id: WidgetResourceType.serviceGroup, name: labelServiceGroup },
      { id: WidgetResourceType.serviceCategory, name: labelServiceCategory },
      { id: WidgetResourceType.service, name: labelService }
    ],
    id: WidgetResourceType.hostCategory,
    name: labelHostCategory
  },
  {
    availableResourceTypeOptions: [
      { id: WidgetResourceType.hostCategory, name: labelHostCategory },
      { id: WidgetResourceType.host, name: labelHost },
      { id: WidgetResourceType.serviceGroup, name: labelServiceGroup },
      { id: WidgetResourceType.serviceCategory, name: labelServiceCategory },
      { id: WidgetResourceType.service, name: labelService }
    ],
    id: WidgetResourceType.hostGroup,
    name: labelHostGroup
  },
  {
    availableResourceTypeOptions: [],
    id: WidgetResourceType.service,
    name: labelService
  },
  {
    availableResourceTypeOptions: [
      { id: WidgetResourceType.serviceGroup, name: labelServiceGroup },
      { id: WidgetResourceType.service, name: labelService }
    ],
    id: WidgetResourceType.serviceCategory,
    name: labelServiceCategory
  },
  {
    availableResourceTypeOptions: [
      { id: WidgetResourceType.serviceCategory, name: labelServiceCategory },
      { id: WidgetResourceType.service, name: labelService }
    ],
    id: WidgetResourceType.serviceGroup,
    name: labelServiceGroup
  }
];

const getAdditionalQueryParameters = (
  resourceType: WidgetResourceType,
  onlyWithPerformanceData = false
): Array<{ name: string; value: unknown }> => [
  {
    name: 'types',
    value: [resourceType]
  },
  {
    name: 'only_with_performance_data',
    value: equals(resourceType, WidgetResourceType.host)
      ? false
      : onlyWithPerformanceData
  },
  {
    name: 'limit',
    value: 30
  }
];

const useResources = ({
  propertyName,
  restrictedResourceTypes,
  required
}: Pick<
  WidgetPropertyProps,
  'propertyName' | 'restrictedResourceTypes' | 'required'
>): UseResourcesState => {
  const { values, setFieldValue, setFieldTouched, touched } =
    useFormikContext<Widget>();

  const value = useMemo<Array<WidgetDataResource> | undefined>(
    () => getDataProperty({ obj: values, propertyName }),
    [getDataProperty({ obj: values, propertyName })]
  );

  const isTouched = useMemo<boolean | undefined>(
    () => getDataProperty({ obj: touched, propertyName }),
    [getDataProperty({ obj: touched, propertyName })]
  );

  const widgetProperties = useAtomValue(
    widgetPropertiesMetaPropertiesDerivedAtom
  );
  const hasMetricInputType = useAtomValue(hasMetricInputTypeDerivedAtom);

  const errorToDisplay =
    isTouched && required && isEmpty(value) ? labelPleaseSelectAResource : null;

  const getResourceStatic = (
    resourceType: WidgetResourceType
  ): boolean | undefined => {
    return (
      widgetProperties?.singleMetricSelection &&
      widgetProperties?.singleHostPerMetric &&
      (equals(resourceType, WidgetResourceType.host) ||
        equals(resourceType, WidgetResourceType.service))
    );
  };

  const changeResourceType =
    (index: number) => (e: ChangeEvent<HTMLInputElement>) => {
      const isNotLastResourceTypeChanged = value?.length || 0 - 1 > index;

      if (isNotLastResourceTypeChanged) {
        const newValue = value?.slice(0, index + 1);
        setFieldValue(`data.${propertyName}`, newValue);
      }

      setFieldValue(
        `data.${propertyName}.${index}.resourceType`,
        e.target.value
      );
      setFieldValue(`data.${propertyName}.${index}.resources`, [], false);
    };

  const changeResources =
    (index: number) => (_, resources: Array<SelectEntry>) => {
      setFieldValue(`data.${propertyName}.${index}.resources`, resources);
      setFieldTouched(`data.${propertyName}`, true, false);
    };

  const changeResource = (index: number) => (_, resource: SelectEntry) => {
    setFieldValue(`data.${propertyName}.${index}.resources`, [resource]);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const addResource = (): void => {
    setFieldValue(`data.${propertyName}`, [
      ...(value || []),
      {
        resourceType: '',
        resources: []
      }
    ]);
  };

  const deleteResource = (index: number | string) => (): void => {
    setFieldValue(
      `data.${propertyName}`,
      (value || []).filter((_, i) => !equals(i, index))
    );
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const deleteResourceItem = ({ index, option, resources }): void => {
    const newResource = reject(propEq(option.id, 'id'), resources);

    setFieldValue(`data.${propertyName}.${index}.resources`, newResource);
    setFieldTouched(`data.${propertyName}`, true, false);
  };

  const getCustomQueryParameters = (
    index: number,
    resourceType
  ): Array<QueryParameter> => {
    const usesResourcesEndpoint = includes(resourceType, [
      WidgetResourceType.host
    ]);
    const isOfTypeService = equals(resourceType, WidgetResourceType.service);
    const isOfTypeCategory = includes(resourceType, [
      WidgetResourceType.hostCategory,
      WidgetResourceType.serviceCategory
    ]);

    if (equals(index, 0)) {
      return usesResourcesEndpoint
        ? getAdditionalQueryParameters(resourceType, hasMetricInputType)
        : [];
    }

    const searchParameter = value?.[index - 1].resourceType as string;
    const searchValues = pluck('name', value?.[index - 1].resources);

    if (!usesResourcesEndpoint && !isOfTypeCategory) {
      const serviceParameters = isOfTypeService
        ? [
            {
              name: 'only_with_performance_data',
              value: hasMetricInputType
            }
          ]
        : [];

      return [
        {
          name: 'search',
          value: {
            [`${searchParameter.replace('-', '_')}.name`]: {
              $in: searchValues
            }
          }
        },
        ...serviceParameters
      ];
    }

    const baseParams = getAdditionalQueryParameters(
      resourceType,
      hasMetricInputType
    );

    return [
      ...baseParams,
      {
        name: includes('category', searchParameter)
          ? `${searchParameter.replace('-', '_')}_names`
          : `${searchParameter.replace('-', '')}_names`,
        value: searchValues
      }
    ];
  };

  const getResourceResourceBaseEndpoint =
    ({ index, resourceType }) =>
    (parameters): string => {
      return buildListingEndpoint({
        baseEndpoint: `${baseEndpoint}/monitoring${resourceTypeBaseEndpoints[resourceType]}`,
        customQueryParameters: getCustomQueryParameters(index, resourceType),
        parameters: {
          ...parameters,
          limit: 30
        }
      });
    };

  const getSearchField = (resourceType: string): string =>
    cond([
      [equals('host'), always('h.name')],
      [T, always('name')]
    ])(resourceType);

  const hasRestrictedTypes = useMemo(
    () =>
      isNotNil(restrictedResourceTypes) && !isEmpty(restrictedResourceTypes),
    [restrictedResourceTypes]
  );

  const resourcetypesIds = pluck('resourceType', value || []);

  const getResourceTypeOptions = (
    index,
    resource
  ): Array<ResourceTypeOption> => {
    const availableResourceTypes =
      index < 1
        ? resourceTypeOptions
        : resourceTypeOptions.find(
            ({ id }) => id === value?.[index - 1].resourceType
          )?.availableResourceTypeOptions;

    const newResourceTypeOptions = filter(
      ({ id }) =>
        hasRestrictedTypes
          ? includes(id, restrictedResourceTypes || [])
          : (includes(id, pluck('id', availableResourceTypes)) &&
              !includes(id, resourcetypesIds)) ||
            equals(id, resource.resourceType),
      resourceTypeOptions
    );

    return newResourceTypeOptions;
  };

  useEffect(() => {
    if (!isEmpty(value)) {
      return;
    }

    if (
      widgetProperties?.singleMetricSelection &&
      widgetProperties?.singleHostPerMetric
    ) {
      setFieldValue(`data.${propertyName}`, [
        {
          resourceType: WidgetResourceType.host,
          resources: []
        },
        {
          resourceType: WidgetResourceType.service,
          resources: []
        }
      ]);

      return;
    }

    setFieldValue(`data.${propertyName}`, [
      {
        resourceType: '',
        resources: []
      }
    ]);
  }, [values.moduleName]);

  const isLastResourceInTree = equals(
    last(resourcetypesIds),
    WidgetResourceType.service
  );

  const changeIdValue = (resourceType): (({ name }) => string) | undefined => {
    const isOfTypeService = equals(resourceType, WidgetResourceType.service);

    if (!isOfTypeService) {
      return undefined;
    }

    return ({ name }) => name;
  };

  return {
    addResource,
    changeIdValue,
    changeResource,
    changeResourceType,
    changeResources,
    deleteResource,
    deleteResourceItem,
    error: errorToDisplay,
    getResourceResourceBaseEndpoint,
    getResourceStatic,
    getResourceTypeOptions,
    getSearchField,
    isLastResourceInTree,
    singleHostPerMetric: widgetProperties?.singleHostPerMetric,
    singleMetricSelection: widgetProperties?.singleMetricSelection,
    value: value || []
  };
};

export default useResources;
