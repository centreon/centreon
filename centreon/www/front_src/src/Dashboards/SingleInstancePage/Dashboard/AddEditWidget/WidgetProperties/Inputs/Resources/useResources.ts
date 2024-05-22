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
  find,
  last,
  filter
} from 'ramda';
import { useAtomValue } from 'jotai';

import {
  QueryParameter,
  SelectEntry,
  buildListingEndpoint
} from '@centreon/ui';
import { additionalResourcesAtom } from '@centreon/ui-context';

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
  singleResourceSelection?: boolean;
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
  [WidgetResourceType.service]: '/resources',
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
  required,
  useAdditionalResources
}: Pick<
  WidgetPropertyProps,
  | 'propertyName'
  | 'restrictedResourceTypes'
  | 'required'
  | 'useAdditionalResources'
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
  const additionalResources = useAtomValue(additionalResourcesAtom);

  const errorToDisplay =
    isTouched && required && isEmpty(value) ? labelPleaseSelectAResource : null;

  const getResourceStatic = (
    resourceType: WidgetResourceType
  ): boolean | undefined => {
    return (
      widgetProperties?.singleMetricSelection &&
      widgetProperties?.singleResourceSelection &&
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
    const isOfTypeHostOrService = includes(resourceType, [
      WidgetResourceType.service,
      WidgetResourceType.host
    ]);

    if (equals(index, 0)) {
      return isOfTypeHostOrService
        ? getAdditionalQueryParameters(resourceType, hasMetricInputType)
        : [];
    }
    const searchParameter = value?.[index - 1].resourceType as string;
    const searchValues = pluck('name', value?.[index - 1].resources);

    if (!isOfTypeHostOrService) {
      return [
        {
          name: 'search',
          value: {
            [`${searchParameter.replace('-', '_')}.name`]: {
              $in: searchValues
            }
          }
        }
      ];
    }

    const baseParams = getAdditionalQueryParameters(
      resourceType,
      hasMetricInputType
    );

    if (equals(searchParameter, WidgetResourceType.host)) {
      return [
        ...baseParams,
        {
          name: 'search',
          value: {
            parent_name: {
              $in: searchValues
            }
          }
        }
      ];
    }

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
      const additionalResource = find(
        ({ resourceType: additionalResourceType }) =>
          equals(resourceType, additionalResourceType),
        additionalResources
      );

      const endpoint = !additionalResource
        ? `${baseEndpoint}/monitoring${resourceTypeBaseEndpoints[resourceType]}`
        : additionalResource?.baseEndpoint;

      const search = !additionalResource?.defaultMonitoringParameter
        ? parameters.search
        : {
            ...parameters.search,
            lists: [
              ...(parameters.search?.lists || []),
              ...Object.entries(
                additionalResource.defaultMonitoringParameter || {}
              ).map(([propertyKey, propertyValue]) => ({
                field: propertyKey,
                values: [propertyValue]
              }))
            ]
          };

      return buildListingEndpoint({
        baseEndpoint: endpoint,
        customQueryParameters: getCustomQueryParameters(index, resourceType),
        parameters: {
          ...parameters,
          limit: 30,
          search
        }
      });
    };

  const getSearchField = (resourceType: string): string =>
    cond([
      [equals('host'), always('host.name')],
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
    const additionalResourceTypeOptions = useAdditionalResources
      ? additionalResources.map(({ resourceType, label }) => ({
          id: resourceType,
          name: label
        }))
      : [];

    const allResourceTypeOptions = [
      ...resourceTypeOptions,
      ...additionalResourceTypeOptions
    ];

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
      allResourceTypeOptions
    );

    return newResourceTypeOptions;
  };

  useEffect(() => {
    if (!isEmpty(value)) {
      return;
    }

    if (
      widgetProperties?.singleMetricSelection &&
      widgetProperties?.singleResourceSelection
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

  return {
    addResource,
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
    singleResourceSelection: widgetProperties?.singleResourceSelection,
    value: value || []
  };
};

export default useResources;
