import { useMemo } from 'react';

import { Layout } from 'react-grid-layout';
import { useAtom, useAtomValue, useSetAtom } from 'jotai';
import {
  equals,
  isEmpty,
  map,
  propEq,
  T,
  always,
  cond,
  flatten,
  groupBy,
  identity,
  uniq
} from 'ramda';
import { useNavigate } from 'react-router';

import { getColumnsFromScreenSize } from '@centreon/ui';

import { dashboardAtom, isEditingAtom, refreshCountsAtom } from '../atoms';
import { Panel } from '../models';
import { editProperties } from '../hooks/useCanEditDashboard';
import { AddEditWidgetModal } from '../AddEditWidget';

import PanelsLayout from './Layout';

import {
  openDetailsTabIdAtom,
  selectedResourceUuidAtom,
  selectedResourcesDetailsAtom
} from 'www/front_src/src/Resources/Details/detailsAtoms';
import { listingAtom } from 'www/front_src/src/Resources/Listing/listingAtoms';

const addWidgetId = 'add_widget_panel';

const emptyLayout: Array<Panel> = [
  {
    h: 3,
    i: addWidgetId,
    name: addWidgetId,
    panelConfiguration: {
      isAddWidgetPanel: true,
      path: ''
    },
    static: true,
    w: 3,
    x: 0,
    y: 0
  }
];

const DashboardPageLayout = (): JSX.Element => {
  const navigate = useNavigate();

  const [dashboard, setDashboard] = useAtom(dashboardAtom);
  const [refreshCounts, setRefreshCounts] = useAtom(refreshCountsAtom);
  const isEditing = useAtomValue(isEditingAtom);
  const setSelectedResourceDetails = useSetAtom(selectedResourcesDetailsAtom);
  const setOpenDetailsTabId = useSetAtom(openDetailsTabIdAtom);
  const setSelectedResourceUuid = useSetAtom(selectedResourceUuidAtom);

  const { canEdit } = editProperties.useCanEditProperties();

  const changeLayout = (layout: Array<Layout>): void => {
    const isOneColumnDisplay = equals(getColumnsFromScreenSize(), 1);
    const isEmptyLayout =
      equals(layout.length, 1) && equals(layout[0].i, addWidgetId);

    if (isOneColumnDisplay || isEmptyLayout) {
      return;
    }

    const newLayout = map<Layout, Panel>((panel) => {
      const currentWidget = dashboard.layout.find(propEq(panel.i, 'i'));

      return {
        ...panel,
        data: currentWidget?.data,
        name: currentWidget?.name,
        options: currentWidget?.options,
        panelConfiguration: currentWidget?.panelConfiguration
      } as Panel;
    }, layout);

    setDashboard({
      layout: newLayout
    });
  };

  const setRefreshCount = (id: string): void => {
    setRefreshCounts((prev) => ({
      ...prev,
      [id]: (prev[id] || 0) + 1
    }));
  };

  const showDefaultLayout = useMemo(
    () => isEmpty(dashboard.layout) && isEditing,
    [dashboard.layout, isEditing]
  );

  const panels = showDefaultLayout
    ? emptyLayout
    : dashboard.layout.map(({ i, ...props }) => {
        return {
          i,
          refreshCount: refreshCounts[i] || 0,
          ...props
        };
      });

  // const getResourcesUrl = ({ resources }): string => {
  //   const groupedResources = groupBy(
  //     ({ resourceType }) => resourceType,
  //     resources
  //   );

  //   const resourcesFilters = Object.entries(groupedResources).map(
  //     ([resourceType, res]) => {
  //       const name = cond<Array<string>, string>([
  //         [equals('host'), always('parent_name')],
  //         [equals('service'), always('name')],
  //         [T, identity]
  //       ])(resourceType);

  //       return {
  //         name: name.replace('-', '_'),
  //         value: flatten(
  //           (res || []).map(({ resources: subResources }) => {
  //             return subResources.map(({ name: resourceName }) => ({
  //               id:
  //                 equals(name, 'name') || equals(name, 'parent_name')
  //                   ? `\\b${resourceName}\\b`
  //                   : resourceName,
  //               name: resourceName
  //             }));
  //           })
  //         )
  //       };
  //     }
  //   );

  //   const serviceCriteria = {
  //     name: 'resource_types',
  //     value: [{ id: 'service', name: 'Service' }]
  //   };

  //   const filterQueryParameter = {
  //     criterias: [
  //       serviceCriteria,
  //       ...resourcesFilters,
  //       { name: 'search', value: '' }
  //     ]
  //   };

  //   return `/monitoring/resources?filter=${JSON.stringify(
  //     filterQueryParameter
  //   )}&fromTopCounter=true`;
  // };

  // const linkToResourceStatus = (panelData, name): void => {
  //   if (!isEditing) {
  //     navigate(
  //       getResourcesUrl({
  //         resources: panelData?.resources
  //       })
  //     );
  //   }
  // };

  const getResourcesUrl = (panelData): string => {
    const values = panelData?.services?.map(({ name }) => {
      const index = name.indexOf('_');

      return {
        id: `\\b${name.slice(index + 1)}\\b`,
        name: name.slice(index + 1)
      };
    });

    const hostvalues = panelData?.services?.map(({ name }) => {
      const index = name.indexOf('_');

      return {
        id: `\\b${name.slice(0, index)}\\b`,
        name: name.slice(0, index)
      };
    });

    const filters = [
      { name: 'name', value: values },
      { name: 'h.name', value: uniq(hostvalues) }
    ];

    const serviceCriteria = {
      name: 'resource_types',
      value: [{ id: 'service', name: 'Service' }]
    };

    const filterQueryParameter = {
      criterias: [serviceCriteria, ...filters, { name: 'search', value: '' }]
    };

    return `/monitoring/resources?filter=${JSON.stringify(
      filterQueryParameter
    )}&fromTopCounter=true`;
  };

  const linkToResourceStatus = (panelData, name): void => {
    navigate(getResourcesUrl(panelData));

    if (equals(name, 'centreon-widget-singlemetric')) {
      const uuid = panelData?.services[0].uuid;

      const hostId = uuid.split('-')[0].slice(1);
      const serviceId = uuid.split('-')[1].slice(1);

      const resourcesDetailsEndpoint = `/centreon/api/latest/monitoring/resources/hosts/${hostId}/services/${serviceId}`;

      setOpenDetailsTabId(3);
      setSelectedResourceUuid(uuid);
      setSelectedResourceDetails({
        resourceId: serviceId,
        resourcesDetailsEndpoint
      });
    }
  };

  return (
    <>
      <PanelsLayout
        displayMoreActions
        canEdit={canEdit}
        changeLayout={changeLayout}
        isEditing={isEditing}
        isStatic={!isEditing || showDefaultLayout}
        linkToResourceStatus={linkToResourceStatus}
        panels={panels}
        setRefreshCount={setRefreshCount}
      />
      <AddEditWidgetModal />
    </>
  );
};

export default DashboardPageLayout;
