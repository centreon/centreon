import { capitalize } from '@mui/material';

import { type Column, SnackbarProvider, TestQueryProvider } from '@centreon/ui';

import { atom, createStore, Provider } from 'jotai';
import { atomWithStorage } from 'jotai/utils';
import { useEffect, useMemo } from 'react';
import { MemoryRouter } from 'react-router';
import { object } from 'yup';

import { LocationRecorder } from '../../../../../playwright/gallery/storyHelpers';
import { type Actions, ResourceType } from '../models';
import ConfigurationBase from '.';
import { panelWidthAtom } from './atoms';
import { defaultFiltersPanelWidth } from './Filters/Filters.styles';
import { getDefaultPanelWidth, maxPanelWidth } from './Panel';
import {
  columns,
  columnsAtomKey,
  filtersAtomKey,
  filtersConfiguration,
  filtersConfigurationWithSingleConnectedAutocomplete,
  filtersInitialValues,
  filtersInitialValuesWithSingleConnectedAutocomplete,
  getEndpoints,
  getHostTemplatesResponse,
  getLabelDeleteMany,
  getLabelDeleteOne,
  getLabelDuplicateMany,
  getLabelDuplicateOne,
  getListingResponse,
  groups,
  hostTemplatesEndpoint,
  inputs,
  resourceDecoderListDecoder
} from './Specs/utils';

// The action sets the Cypress specs declare, by name: functions cannot be
// passed as story props.
const actionPresets: Record<string, Actions> = {
  default: {
    delete: () => true,
    duplicate: () => true,
    edit: true,
    enableDisable: () => true,
    massive: true,
    viewDetails: true
  },
  deleteOnly: { delete: () => true, edit: true },
  detailsOnly: {
    delete: () => false,
    duplicate: () => false,
    edit: false,
    enableDisable: () => false,
    viewDetails: true
  },
  editOnly: { edit: true },
  noEditNorDetails: {
    delete: () => true,
    duplicate: () => true,
    edit: false,
    enableDisable: () => true,
    massive: true,
    viewDetails: false
  },
  rowAction: {
    edit: true,
    rowActions: [
      {
        dataTestId: (row) => `custom-action_${row?.id}`,
        Icon: () => <span>action</span>,
        isVisible: (row) => row?.id === 1,
        label: 'Custom action',
        onClick: () => undefined
      }
    ]
  },
  toggleOnFirst: { edit: true, enableDisable: (row) => row?.id === 1 },
  toggleOnFirstWithoutWriteAccess: {
    edit: true,
    enableDisable: (row) => row?.id === 1,
    rowActionsWithoutWriteAccess: true
  }
} as Record<string, Actions>;

const filterPresets = {
  default: {
    filters: filtersConfiguration,
    initialValues: filtersInitialValues
  },
  onlyName: {
    filters: filtersConfiguration.filter(({ name }) => name === 'Name'),
    initialValues: filtersInitialValues
  },
  singleConnectedAutocomplete: {
    filters: filtersConfigurationWithSingleConnectedAutocomplete,
    initialValues: filtersInitialValuesWithSingleConnectedAutocomplete
  }
};

interface Props {
  actions?: keyof typeof actionPresets;
  filters?: keyof typeof filterPresets;
  filtersPanelWidth?: number;
  formPanelWidth?: number;
  formVariant?: 'modal' | 'panel';
  /** The URL the page opens on, for deep links. */
  initialEntry?: string;
  panelWidth?: number;
  resourceType?: ResourceType;
}

export const Default = ({
  actions = 'default',
  filters = 'default',
  filtersPanelWidth,
  formPanelWidth,
  formVariant,
  initialEntry = '/',
  panelWidth,
  resourceType = ResourceType.Host
}: Props): JSX.Element => {
  const resource = resourceType.replace(' ', '_');
  const {
    filtersAtom,
    isWelcomePageDisplayedAtom,
    selectedColumnIdsAtom,
    store
  } = useMemo(() => {
    const created = createStore();
    created.set(panelWidthAtom, panelWidth ?? null);

    return {
      filtersAtom: atomWithStorage(
        filtersAtomKey,
        filterPresets[filters].initialValues
      ),
      isWelcomePageDisplayedAtom: atom(false),
      selectedColumnIdsAtom: atomWithStorage<Array<string>>(columnsAtomKey, []),
      store: created
    };
  }, [filters, panelWidth]);

  return (
    <MemoryRouter initialEntries={[initialEntry]}>
      <SnackbarProvider>
        <TestQueryProvider>
          <Provider store={store}>
            <div style={{ height: '100vh' }}>
              <ConfigurationBase
                actions={actionPresets[actions]}
                api={{
                  adapter: (data) => data,
                  decoders: { getAll: resourceDecoderListDecoder },
                  endpoints: getEndpoints(resource)
                }}
                // The Cypress test data is untyped.
                columns={columns as unknown as Array<Column>}
                columnsAtomKey={columnsAtomKey}
                defaultSelectedColumnIds={[
                  'name',
                  'alias',
                  'actions',
                  'is_activated'
                ]}
                filtersAtom={filtersAtom}
                filtersAtomKey={filtersAtomKey}
                filtersConfiguration={filterPresets[filters].filters}
                filtersInitialValues={filterPresets[filters].initialValues}
                filtersPanelWidth={filtersPanelWidth}
                form={{
                  defaultValues: { alias: '', coordinates: '', name: '' },
                  groups,
                  inputs,
                  // None in the Cypress mount either.
                  validationSchema: object()
                }}
                formPanelWidth={formPanelWidth}
                formVariant={formVariant}
                isWelcomePageDisplayedAtom={isWelcomePageDisplayedAtom}
                labels={{
                  title: `${capitalize(resourceType)}s`,
                  welcomePage: {
                    actions: { create: 'Add configuration base' },
                    title: 'Welcome to configuration base'
                  }
                }}
                resourceType={resourceType}
                selectedColumnIdsAtom={selectedColumnIdsAtom}
              />
            </div>
            <LocationRecorder />
          </Provider>
        </TestQueryProvider>
      </SnackbarProvider>
    </MemoryRouter>
  );
};

const resourceData = (resourceType: ResourceType) => {
  const resource = resourceType.replace(' ', '_');
  const endpoints = getEndpoints(resource);

  return {
    endpoints: {
      create: endpoints.create,
      delete: endpoints.delete,
      disable: endpoints.disable?.({ id: 1 }),
      duplicate: endpoints.duplicate,
      enable: endpoints.enable?.({ id: 1 }),
      getAll: endpoints.getAll,
      one: endpoints.getOne?.({ id: 1 })
    },
    labels: {
      deleteMany: getLabelDeleteMany(`${resourceType}s`, 3),
      deleteOne: getLabelDeleteOne(resourceType, `${resource} 1`),
      duplicateMany: getLabelDuplicateMany(`${resourceType}s`, 3),
      duplicateOne: getLabelDuplicateOne(resourceType, `${resource} 1`)
    },
    listing: getListingResponse(resource)
  };
};

const data = {
  defaultFiltersPanelWidth,
  defaultPanelWidths: Object.fromEntries(
    [1280, 1440, 1512, 1920].map((viewport) => [
      viewport,
      getDefaultPanelWidth(viewport)
    ])
  ),
  groups: groups.map(({ name }) => name),
  hostTemplates: getHostTemplatesResponse(),
  hostTemplatesEndpoint,
  inputs: inputs.map(({ fieldName, label }) => ({ fieldName, label })),
  maxPanelWidth,
  resources: {
    [ResourceType.Host]: resourceData(ResourceType.Host),
    [ResourceType.HostGroup]: resourceData(ResourceType.HostGroup)
  }
};

declare global {
  interface Window {
    configurationBaseData?: typeof data;
  }
}

// Data story: the test data module reaches @centreon/ui, which Node cannot
// load, so specs read it from the browser.
export const Data = (): JSX.Element => {
  useEffect(() => {
    window.configurationBaseData = data;
  }, []);

  return <p>configuration base data ready</p>;
};
