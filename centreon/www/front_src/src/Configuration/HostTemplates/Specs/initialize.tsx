import { Form, SnackbarProvider } from '@centreon/ui';
import {
  defaultLimitPerPageAtom,
  type PlatformFeatures,
  platformFeaturesAtom,
  type UserPermissions,
  userPermissionsAtom
} from '@centreon/ui-context';

import {
  QueryCache,
  QueryClient,
  QueryClientProvider
} from '@tanstack/react-query';
import { interceptAPIRequest, Method } from '@test-setup/api';
import { render } from '@test-setup/render';
import type { RenderResult } from '@testing-library/react';
import i18next from 'i18next';
import { createStore, Provider } from 'jotai';
import type { ReactElement } from 'react';
import { initReactI18next } from 'react-i18next';
import { BrowserRouter as Router } from 'react-router';
import { object } from 'yup';

import HostTemplates from '..';
import {
  getDuplicateHostTemplateEndpoint,
  getHostTemplateEndpoint,
  getHostTemplateMacrosEndpoint,
  hostTemplateFormCommandsEndpoint,
  hostTemplateFormContactGroupsEndpoint,
  hostTemplateFormContactsEndpoint,
  hostTemplateFormHostCategoriesEndpoint,
  hostTemplateFormHostSeveritiesEndpoint,
  hostTemplateFormHostTemplatesEndpoint,
  hostTemplateFormMediasEndpoint,
  hostTemplateFormServiceTemplatesEndpoint,
  hostTemplateFormTimePeriodsEndpoint,
  hostTemplatesListEndpoint,
  timezonesEndpoint
} from '../api/endpoints';
import { getDefaultValues, useFormInputs } from '../Form';
import {
  emptyListingResponse,
  getFormCommandsResponse,
  getFormContactGroupsResponse,
  getFormContactsResponse,
  getFormHostCategoriesResponse,
  getFormHostSeveritiesResponse,
  getFormMediasResponse,
  getFormParentTemplatesResponse,
  getFormServiceTemplatesResponse,
  getFormTimePeriodsResponse,
  getHostTemplateResponse,
  getListingResponse,
  getTemplateMacrosResponses,
  getTimezonesResponse
} from './utils';

interface Props {
  isEmpty?: boolean;
  hasWriteAccess?: boolean;
  isCloudPlatform?: boolean;
  search?: string;
}

interface InitializeResult extends RenderResult {
  // Failed queries by key, including ones that never sent a request.
  queryErrors: Array<{ error: Error; queryKey: ReadonlyArray<unknown> }>;
  store: ReturnType<typeof createStore>;
}

// The header cells carry no role: their labels, in order, read from the head.
export const getHeaderLabels = (): Array<string | null> =>
  Array.from(
    document.querySelectorAll('.MuiTableHead-root .MuiTypography-root')
  ).map(({ textContent }) => textContent);

const initialize = ({
  isEmpty = false,
  hasWriteAccess = true,
  isCloudPlatform = false,
  search = ''
}: Props = {}): InitializeResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  // The form is URL-driven, and the page is not reloaded between tests.
  window.history.pushState({}, '', `${window.location.pathname}${search}`);

  const store = createStore();

  // Only what the module reads.
  store.set(userPermissionsAtom, {
    configuration_host_template_write: hasWriteAccess
  } as UserPermissions);
  store.set(defaultLimitPerPageAtom, 10);
  store.set(platformFeaturesAtom, { isCloudPlatform } as PlatformFeatures);

  interceptAPIRequest({
    alias: 'getAllHostTemplates',
    method: Method.GET,
    path: `**${hostTemplatesListEndpoint}?**`,
    response: isEmpty ? emptyListingResponse : getListingResponse()
  });

  // Every row, the locked one included: a write to it must show in the calls.
  [1, 2, 3].forEach((id) => {
    interceptAPIRequest({
      alias: `deleteHostTemplate${id}`,
      method: Method.DELETE,
      path: `**${getHostTemplateEndpoint({ id })}`,
      response: {}
    });
    interceptAPIRequest({
      alias: `duplicateHostTemplate${id}`,
      method: Method.POST,
      path: `**${getDuplicateHostTemplateEndpoint({ id })}`,
      response: {}
    });
    interceptAPIRequest({
      alias: `updateHostTemplate${id}`,
      method: Method.PATCH,
      path: `**${getHostTemplateEndpoint({ id })}`,
      // No body, as the server answers a partial update.
      statusCode: 204
    });
  });

  // Every template the listing names opens the form: its rows and their parents.
  [1, 2, 3, 4].forEach((id) => {
    interceptAPIRequest({
      alias: `getHostTemplate${id}`,
      method: Method.GET,
      path: `**${getHostTemplateEndpoint({ id })}`,
      response: getHostTemplateResponse(id)
    });
  });

  // The form's selectors.
  [
    {
      alias: 'getFormCommands',
      path: hostTemplateFormCommandsEndpoint,
      response: getFormCommandsResponse()
    },
    {
      alias: 'getFormContacts',
      path: hostTemplateFormContactsEndpoint,
      response: getFormContactsResponse()
    },
    {
      alias: 'getFormContactGroups',
      path: hostTemplateFormContactGroupsEndpoint,
      response: getFormContactGroupsResponse()
    },
    {
      alias: 'getFormHostCategories',
      path: hostTemplateFormHostCategoriesEndpoint,
      response: getFormHostCategoriesResponse()
    },
    {
      alias: 'getFormHostSeverities',
      path: hostTemplateFormHostSeveritiesEndpoint,
      response: getFormHostSeveritiesResponse()
    },
    {
      alias: 'getFormServiceTemplates',
      path: hostTemplateFormServiceTemplatesEndpoint,
      response: getFormServiceTemplatesResponse()
    },
    {
      alias: 'getFormMedias',
      path: hostTemplateFormMediasEndpoint,
      response: getFormMediasResponse()
    },
    {
      alias: 'getFormTimePeriods',
      path: hostTemplateFormTimePeriodsEndpoint,
      response: getFormTimePeriodsResponse()
    },
    {
      alias: 'getFormTimezones',
      path: timezonesEndpoint,
      response: getTimezonesResponse()
    }
  ].forEach(({ alias, path, response }) => {
    interceptAPIRequest({
      alias,
      method: Method.GET,
      path: `**${path}?**`,
      response
    });
  });

  interceptAPIRequest({
    alias: 'getFormParentTemplates',
    method: Method.GET,
    path: `**${hostTemplateFormHostTemplatesEndpoint}?**`,
    response: getFormParentTemplatesResponse()
  });
  // Declared after the one above, so it answers first when the query matches.
  interceptAPIRequest({
    alias: 'getFormParentTemplatesExcluding1',
    method: Method.GET,
    path: `**${hostTemplateFormHostTemplatesEndpoint}?**`,
    query: { name: 'exclude_template_id', value: '1' },
    response: getFormParentTemplatesResponse(1)
  });

  Object.entries(getTemplateMacrosResponses()).forEach(([id, response]) => {
    interceptAPIRequest({
      alias: `getTemplateMacros${id}`,
      method: Method.GET,
      path: `**${getHostTemplateMacrosEndpoint({ id: Number(id) })}`,
      response
    });
  });

  interceptAPIRequest({
    alias: 'createHostTemplate',
    method: Method.POST,
    path: `**${hostTemplatesListEndpoint}`,
    response: { id: 4, name: 'new-template' }
  });

  const queryErrors: InitializeResult['queryErrors'] = [];

  // A client of its own, so no cache leaks from one test to the next.
  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } },
    queryCache: new QueryCache({
      onError: (error, { queryKey }) => {
        queryErrors.push({ error, queryKey });
      }
    })
  });

  const renderResult = render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          <Provider store={store}>
            <div className="h-screen">
              <HostTemplates />
            </div>
          </Provider>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );

  return { ...renderResult, queryErrors, store };
};

interface FormLayoutProps {
  isCloudPlatform?: boolean;
}

const FormLayout = ({
  isCloudPlatform = false
}: FormLayoutProps): ReactElement => {
  const { groups, inputs } = useFormInputs({
    canEdit: true,
    isAdditiveInheritanceEnabled: false,
    isCloudPlatform
  });

  return (
    <Form
      areGroupsOpen
      groups={groups}
      initialValues={getDefaultValues({ isCloudPlatform })}
      inputs={inputs}
      isCollapsible
      submit={() => undefined}
      validationSchema={object()}
    />
  );
};

// The narrowest and widest panel (`ConfigurationBase/Panel`), the widest one
// wider than the page can open it.
const panelWidthClassNames = {
  narrow: 'w-[720px]',
  wide: 'w-[1400px]'
};

// The form alone, laid out against a panel of the given width.
export const initializeFormLayout = ({
  width,
  ...props
}: FormLayoutProps & {
  width: keyof typeof panelWidthClassNames;
}): RenderResult => {
  i18next.use(initReactI18next).init({
    lng: 'en',
    resources: {}
  });

  const queryClient = new QueryClient({
    defaultOptions: { queries: { gcTime: 0, retry: false } }
  });

  return render(
    <Router>
      <SnackbarProvider>
        <QueryClientProvider client={queryClient}>
          {/* The panel content's own classes (`ConfigurationBase/Panel`). */}
          <div className={`@container px-5 ${panelWidthClassNames[width]}`}>
            <FormLayout {...props} />
          </div>
        </QueryClientProvider>
      </SnackbarProvider>
    </Router>
  );
};

export default initialize;
