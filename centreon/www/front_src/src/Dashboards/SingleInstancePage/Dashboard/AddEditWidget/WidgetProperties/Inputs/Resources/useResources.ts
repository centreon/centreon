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
  find
} from 'ramda';
import { useAtomValue } from 'jotai';

import { SelectEntry, buildListingEndpoint } from '@centreon/ui';
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
  getResourceResourceBaseEndpoint: (
    resourceType: string
  ) => (parameters) => string;
  getResourceStatic: (resourceType: WidgetResourceType) => boolean | undefined;
  getResourceTypeOptions: (resource) => Array<ResourceTypeOption>;
  getSearchField: (resourceType: WidgetResourceType) => string;
  singleResourceSelection?: boolean;
  value: Array<WidgetDataResource>;
}

interface ResourceTypeOption {
  id: WidgetResourceType;
  name: string;
}

const resourceTypeOptions = [
  {
    id: WidgetResourceType.hostGroup,
    name: labelHostGroup
  },
  {
    id: WidgetResourceType.hostCategory,
    name: labelHostCategory
  },
  {
    id: WidgetResourceType.host,
    name: labelHost
  },
  {
    id: WidgetResourceType.serviceGroup,
    name: labelServiceGroup
  },
  {
    id: WidgetResourceType.serviceCategory,
    name: labelServiceCategory
  },
  {
    id: WidgetResourceType.service,
    name: labelService
  }
];

export const resourceTypeBaseEndpoints = {
  [WidgetResourceType.host]: '/hosts',
  [WidgetResourceType.hostCategory]: '/hosts/categories',
  [WidgetResourceType.hostGroup]: '/hostgroups',
  [WidgetResourceType.service]: '/resources',
  [WidgetResourceType.serviceCategory]: '/services/categories',
  [WidgetResourceType.serviceGroup]: '/servicegroups'
};

const getServiceQueryParameters = (
  onlyWithPerformanceData = false
): Array<{ name: string; value: unknown }> => [
  {
    name: 'types',
    value: ['service']
  },
  {
    name: 'only_with_performance_data',
    value: onlyWithPerformanceData
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

  const getResourceResourceBaseEndpoint =
    (resourceType: string) =>
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
        customQueryParameters: equals(resourceType, WidgetResourceType.service)
          ? getServiceQueryParameters(hasMetricInputType)
          : undefined,
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

  const getResourceTypeOptions = (resource): Array<ResourceTypeOption> => {
    const additionalResourceTypeOptions = useAdditionalResources
      ? additionalResources.map(({ resourceType, label }) => ({
          id: resourceType,
          name: label
        }))
      : [];

    const resourcetypesIds = pluck('resourceType', value || []);

    const allResourceTypeOptions = [
      ...resourceTypeOptions,
      ...additionalResourceTypeOptions
    ];

    const newResourceTypeOptions = reject(
      ({ id }) =>
        (!equals(id, resource.resourceType) &&
          includes(id, resourcetypesIds)) ||
        (hasRestrictedTypes && !includes(id, restrictedResourceTypes || [])),
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
    singleResourceSelection: widgetProperties?.singleResourceSelection,
    value: value || []
  };
};

export default useResources;
