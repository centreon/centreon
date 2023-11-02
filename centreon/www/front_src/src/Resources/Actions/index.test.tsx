import userEvent from '@testing-library/user-event';
import axios from 'axios';
import { createStore, Provider } from 'jotai';
import mockDate from 'mockdate';
import { equals, head, last, map, pick } from 'ramda';

import { SeverityCode, TestQueryProvider } from '@centreon/ui';
import {
  acknowledgementAtom,
  aclAtom,
  downtimeAtom,
  refreshIntervalAtom,
  userAtom
} from '@centreon/ui-context';
import {
  act,
  fireEvent,
  getFetchCall,
  mockResponse,
  render,
  RenderResult,
  resetMocks,
  screen,
  waitFor
} from '@centreon/ui/test/testRenderer';

import useDetails from '../Details/useDetails';
import useListing from '../Listing/useListing';
import useLoadResources from '../Listing/useLoadResources';
import { Resource } from '../models';
import Context, { ResourceContext } from '../testUtils/Context';
import useActions from '../testUtils/useActions';
import useFilter from '../testUtils/useFilter';
import useLoadDetails from '../testUtils/useLoadDetails';
import {
  labelAcknowledge,
  labelAcknowledgedBy,
  labelAcknowledgeServices,
  labelAddComment,
  labelCheck,
  labelCritical,
  labelDisableAutorefresh,
  labelDisacknowledge,
  labelDisacknowledgeServices,
  labelDown,
  labelDowntimeBy,
  labelDuration,
  labelEnableAutorefresh,
  labelEndTime,
  labelFixed,
  labelForcedCheck,
  labelHostsDenied,
  labelMoreActions,
  labelNotify,
  labelOk,
  labelOutput,
  labelPerformanceData,
  labelRefresh,
  labelSetDowntime,
  labelSetDowntimeOnServices,
  labelStartTime,
  labelSubmit,
  labelSubmitStatus,
  labelUnknown,
  labelUnreachable,
  labelUp,
  labelWarning
} from '../translatedLabels';

import { acknowledgeEndpoint, checkEndpoint } from './api/endpoint';
import { disacknowledgeEndpoint } from './Resource/Disacknowledge/api';
import { submitStatusEndpoint } from './Resource/SubmitStatus/api';

import Actions from '.';

const mockedAxios = axios as jest.Mocked<typeof axios>;

const onRefresh = jest.fn();

jest.mock('@centreon/ui-context', () =>
  jest.requireActual('@centreon/ui-context')
);

const mockUser = {
  alias: 'admin',
  isExportButtonEnabled: true,
  locale: 'en',
  timezone: 'Europe/Paris'
};
const mockRefreshInterval = 15;
const mockDowntime = {
  duration: 7200,
  fixed: true,
  with_services: false
};
const mockAcl = {
  actions: {
    host: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      forced_check: true,
      submit_status: true
    },
    service: {
      acknowledgement: true,
      check: true,
      comment: true,
      disacknowledgement: true,
      downtime: true,
      forced_check: true,
      submit_status: true
    }
  }
};
const mockAcknowledgement = {
  force_active_checks: false,
  notify: false,
  persistent: true,
  sticky: true,
  with_services: true
};

jest.mock('../icons/Downtime');

interface UseMediaQueryListing {
  applyBreakPoint: boolean;
}

jest.mock('./Resource/useMediaQueryListing', () => {
  const originalModule = jest.requireActual('./Resource/useMediaQueryListing');

  return {
    __esModule: true,
    ...originalModule,
    default: (): UseMediaQueryListing => ({
      applyBreakPoint: false
    })
  };
});

const ActionsWithLoading = (): JSX.Element => {
  useLoadResources();

  return (
    <TestQueryProvider>
      <Actions onRefresh={onRefresh} />
    </TestQueryProvider>
  );
};

let context: ResourceContext;

const host = {
  has_passive_checks_enabled: true,
  id: 0,
  parent: null,
  type: 'host'
} as Resource;

const service = {
  has_passive_checks_enabled: true,
  id: 1,
  parent: {
    id: 1
  },
  type: 'service'
} as Resource;

const ActionsWithContext = (): JSX.Element => {
  const detailsState = useLoadDetails();
  const listingState = useListing();
  const actionsState = useActions();
  const filterState = useFilter();

  useDetails();

  context = {
    ...detailsState,
    ...listingState,
    ...actionsState,
    ...filterState
  } as ResourceContext;

  return (
    <Context.Provider key="context" value={context}>
      <ActionsWithLoading />
    </Context.Provider>
  );
};

const renderActions = (aclAtions = mockAcl): RenderResult => {
  const store = createStore();
  store.set(userAtom, mockUser);
  store.set(refreshIntervalAtom, mockRefreshInterval);
  store.set(downtimeAtom, mockDowntime);
  store.set(aclAtom, aclAtions);
  store.set(acknowledgementAtom, mockAcknowledgement);

  return render(
    <Provider store={store}>
      <ActionsWithContext />
    </Provider>
  );
};

describe(Actions, () => {
  const labelAcknowledgedByAdmin = `${labelAcknowledgedBy} admin`;
  const labelDowntimeByAdmin = `${labelDowntimeBy} admin`;

  const mockNow = '2020-01-01';

  beforeEach(() => {
    Object.defineProperty(window, 'matchMedia', {
      value: (query: string): MediaQueryList => ({
        addEventListener: (): void => undefined,

        addListener: (): void => undefined,

        dispatchEvent: (): boolean => true,
        // this is the media query that @material-ui/pickers uses to determine if a device is a desktop device
        matches: query === '(pointer: fine)',
        media: query,
        onchange: (): void => undefined,
        removeEventListener: (): void => undefined,
        removeListener: (): void => undefined
      }),
      writable: true
    });

    mockedAxios.post.mockReset();
    mockedAxios.get.mockResolvedValue({
      data: {
        meta: {
          limit: 30,
          page: 1,
          total: 0
        },
        result: []
      }
    });

    mockDate.set(mockNow);
    onRefresh.mockReset();
    mockResponse({ data: {} });
  });

  afterEach(() => {
    // eslint-disable-next-line @typescript-eslint/ban-ts-comment
    // @ts-ignore
    delete window.matchMedia;

    mockDate.reset();
    mockedAxios.get.mockReset();
    resetMocks();
  });

  it('executes a listing request when the refresh button is clicked', async () => {
    renderActions();

    await waitFor(() => expect(mockedAxios.get).toHaveBeenCalled());

    mockedAxios.get.mockResolvedValueOnce({ data: {} });

    await waitFor(() => {
      expect(screen.getByLabelText(labelRefresh)).toBeInTheDocument();
    });

    const refreshButton = screen.getByLabelText(labelRefresh);

    await waitFor(() => expect(refreshButton).toBeEnabled());

    userEvent.click(refreshButton);

    expect(onRefresh).toHaveBeenCalled();
  });

  it('swaps autorefresh icon when the icon is clicked', async () => {
    const { getByLabelText } = renderActions();

    await waitFor(() => expect(mockedAxios.get).toHaveBeenCalled());

    fireEvent.click(
      getByLabelText(labelDisableAutorefresh).firstElementChild as HTMLElement
    );

    expect(getByLabelText(labelEnableAutorefresh)).toBeTruthy();

    fireEvent.click(
      getByLabelText(labelEnableAutorefresh).firstElementChild as HTMLElement
    );

    expect(getByLabelText(labelDisableAutorefresh)).toBeTruthy();
  });

  it.each([
    [labelAcknowledge, labelAcknowledgedByAdmin, labelAcknowledge],
    [labelSetDowntime, labelDowntimeByAdmin, labelSetDowntime]
  ])(
    'cannot send a %p request when the corresponding action is fired and the comment field is left empty',
    async (labelAction, labelComment, labelConfirmAction) => {
      const { getByText, getAllByText, findByText } = renderActions();

      const selectedResources = [host];

      act(() => {
        context.setSelectedResources?.(selectedResources);
      });

      await waitFor(() =>
        expect(context.selectedResources).toEqual(selectedResources)
      );

      fireEvent.click(getByText(labelAction));

      const commentField = await findByText(labelComment);

      userEvent.clear(commentField);

      await waitFor(() =>
        expect(
          last<HTMLElement>(getAllByText(labelConfirmAction)) as HTMLElement
        ).toBeDisabled()
      );
    }
  );

  it('sends an acknowledgement request when Resources are selected and the Ackowledgement action is clicked and confirmed', async () => {
    const { getByText, findByLabelText, getAllByText } = renderActions();

    const selectedResources = [host, service];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelAcknowledge)).toBeInTheDocument();
    });

    fireEvent.click(getByText(labelAcknowledge));

    const notifyCheckbox = await findByLabelText(labelNotify);

    fireEvent.click(notifyCheckbox);

    mockedAxios.get.mockResolvedValueOnce({ data: {} });
    mockedAxios.post.mockResolvedValueOnce({});

    fireEvent.click(last(getAllByText(labelAcknowledge)) as HTMLElement);

    await waitFor(() =>
      expect(mockedAxios.post).toHaveBeenCalledWith(
        acknowledgeEndpoint,
        {
          acknowledgement: {
            comment: labelAcknowledgedByAdmin,
            is_notify_contacts: true,
            is_persistent_comment: true,
            is_sticky: true,
            with_services: true
          },

          resources: map(pick(['type', 'id', 'parent']), selectedResources)
        },
        expect.anything()
      )
    );
  });

  it('sends a discknowledgement request when Resources are selected and the Disackowledgement action is clicked and confirmed', async () => {
    const { getByLabelText, getAllByText, getByText } = renderActions();

    const selectedResources = [host];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        getByLabelText(labelMoreActions).firstChild as HTMLElement
      ).toBeInTheDocument();
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);

    fireEvent.click(getByText(labelDisacknowledge));

    mockedAxios.delete.mockResolvedValueOnce({});

    fireEvent.click(last(getAllByText(labelDisacknowledge)) as HTMLElement);

    await waitFor(() =>
      expect(mockedAxios.delete).toHaveBeenCalledWith(disacknowledgeEndpoint, {
        cancelToken: expect.anything(),
        data: {
          disacknowledgement: {
            with_services: true
          },

          resources: map(pick(['type', 'id', 'parent']), selectedResources)
        }
      })
    );
  });

  it('does not display the "Acknowledge services attached to host" checkbox when only services are selected and the Acknowledge action is clicked', async () => {
    const { getByText, findByText, queryByText } = renderActions();

    const selectedResources = [service];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelAcknowledge)).toBeInTheDocument();
    });

    fireEvent.click(getByText(labelAcknowledge));

    await findByText(labelAcknowledgedByAdmin);

    expect(queryByText(labelAcknowledgeServices)).toBeNull();
  });

  it('does not display the "Discknowledge services attached to host" checkbox when only services are selected and the Disacknowledge action is clicked', async () => {
    const { getByText, queryByText, getByLabelText } = renderActions();

    const selectedResources = [service];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        getByLabelText(labelMoreActions).firstChild as HTMLElement
      ).toBeInTheDocument();
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);

    fireEvent.click(getByText(labelDisacknowledge));

    await waitFor(() => {
      expect(queryByText(labelDisacknowledgeServices)).toBeNull();
    });
  });

  it('cannot send a downtime request when Downtime action is clicked, type is flexible and duration is empty', async () => {
    const { findByText, getAllByText, getByLabelText } = renderActions();

    const selectedResources = [host];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement
      ).toBeInTheDocument();
    });

    fireEvent.click(last(getAllByText(labelSetDowntime)) as HTMLElement);

    await findByText(labelDowntimeByAdmin);

    await waitFor(() => {
      expect(getByLabelText(labelDuration)).toBeInTheDocument();
    });

    fireEvent.click(getByLabelText(labelFixed));
    fireEvent.change(getByLabelText(labelDuration), {
      target: { value: '' }
    });

    await waitFor(() =>
      expect(last(getAllByText(labelSetDowntime)) as HTMLElement).toBeDisabled()
    );
  });

  it('cannot send a downtime request when Downtime action is clicked and start date is greater than end date', async () => {
    const { getAllByText, findByText, getByLabelText } = renderActions();

    const selectedResources = [host];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        head(getAllByText(labelSetDowntime)) as HTMLElement
      ).toBeInTheDocument();
    });

    fireEvent.click(head(getAllByText(labelSetDowntime)) as HTMLElement);

    await findByText(labelDowntimeByAdmin);

    const inputFieldStartTime = getByLabelText(labelStartTime).querySelector(
      'input'
    ) as HTMLElement;

    const inputFieldEndTime = getByLabelText(labelEndTime).querySelector(
      'input'
    ) as HTMLElement;

    fireEvent.change(inputFieldStartTime, {
      target: { value: '05/03/2019 12:34 AM' }
    });

    fireEvent.change(inputFieldEndTime, {
      target: { value: '05/02/2019 12:34 AM' }
    });

    await waitFor(() =>
      expect(last(getAllByText(labelSetDowntime)) as HTMLElement).toBeDisabled()
    );
  });

  it('sends a downtime request when Resources are selected and the Downtime action is clicked and confirmed', async () => {
    const { findAllByText, getAllByText } = renderActions();

    const selectedResources = [host];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(
        last(getAllByText(labelSetDowntime)) as HTMLElement
      ).toBeInTheDocument();
    });

    fireEvent.click(last(getAllByText(labelSetDowntime)) as HTMLElement);

    mockedAxios.get.mockResolvedValueOnce({ data: {} });
    mockedAxios.post.mockResolvedValueOnce({});

    await findAllByText(labelDowntimeByAdmin);

    await waitFor(() => {
      expect(last(getAllByText(labelSetDowntime)) as HTMLElement).toBeEnabled();
    });
  });

  it.each([
    [labelForcedCheck, { is_forced: true }],
    [labelCheck, { is_forced: false }]
  ])(
    'sends a %p request when Resources are selected and the action is selected',
    async (label, { is_forced }) => {
      const { getByText, findByText, getByLabelText, getAllByText } =
        renderActions();

      await waitFor(() => {
        expect(getByText(labelForcedCheck)).toBeInTheDocument();
      });
      const selectedResources = [host, service];

      mockedAxios.get.mockResolvedValueOnce({ data: {} });
      mockedAxios.all.mockResolvedValueOnce([]);

      act(() => {
        context.setSelectedResources?.(selectedResources);
      });

      await findByText(labelForcedCheck);
      fireEvent.click(getByLabelText('arrow').firstElementChild as HTMLElement);
      await waitFor(() => {
        expect(getByText(labelCheck)).toBeInTheDocument();
        expect(getAllByText(labelForcedCheck)[1]).toBeInTheDocument();
      });
      const selectedLabel = equals(label, labelForcedCheck)
        ? getAllByText(label)[1]
        : getByText(label);

      fireEvent.click(selectedLabel);
      fireEvent.click(getByLabelText('arrow').firstElementChild as HTMLElement);
      fireEvent.click(getByLabelText(label).firstElementChild as HTMLElement);

      const payload = {
        check: { is_forced },
        resources: map(pick(['id', 'parent', 'type']), selectedResources)
      };

      await waitFor(() => {
        expect(getFetchCall(0)).toEqual(`${checkEndpoint}`);
        expect(getFetchCall(0, 1)?.body).toEqual(JSON.stringify(payload));
      });
    }
  );

  it('sends a submit status request when a Resource is selected and the Submit status action is clicked', async () => {
    mockedAxios.post.mockResolvedValueOnce({});

    const { getByText, getByLabelText, getAllByText } = renderActions();

    act(() => {
      context.setSelectedResources?.([service]);
    });

    await waitFor(() => {
      expect(
        getByLabelText(labelMoreActions).firstElementChild as HTMLElement
      ).toBeInTheDocument();
    });

    fireEvent.click(
      getByLabelText(labelMoreActions).firstElementChild as HTMLElement
    );

    fireEvent.click(getByText(labelSubmitStatus) as HTMLElement);

    userEvent.click(getByText(labelOk));

    await waitFor(() => {
      expect(getByText(labelWarning)).toBeInTheDocument();
      expect(getByText(labelCritical)).toBeInTheDocument();
      expect(getByText(labelUnknown)).toBeInTheDocument();
    });

    userEvent.click(getByText(labelWarning));

    const output = 'output';
    const performanceData = 'performance data';

    fireEvent.change(getByLabelText(labelOutput), {
      target: {
        value: output
      }
    });

    fireEvent.change(getByLabelText(labelPerformanceData), {
      target: {
        value: performanceData
      }
    });

    fireEvent.click(getByText(labelSubmit));

    await waitFor(() => {
      expect(mockedAxios.post).toHaveBeenCalledWith(
        submitStatusEndpoint,
        {
          resources: [
            {
              ...pick(['type', 'id', 'parent'], service),
              output,
              performance_data: performanceData,
              status: 1
            }
          ]
        },
        expect.anything()
      );
    });

    act(() => {
      context.setSelectedResources?.([host]);
    });

    fireEvent.click(
      getByLabelText(labelMoreActions).firstElementChild as HTMLElement
    );

    fireEvent.click(getAllByText(labelSubmitStatus)[1] as HTMLElement);

    userEvent.click(getByText(labelUp));

    await waitFor(() => {
      expect(getByText(labelDown)).toBeInTheDocument();
      expect(getByText(labelUnreachable)).toBeInTheDocument();
    });
  });

  it('cannot execute an action when associated ACL are not sufficient', async () => {
    const { getByText, getByLabelText } = renderActions({
      actions: {
        host: {
          acknowledgement: false,
          check: false,
          comment: false,
          disacknowledgement: false,
          downtime: false,
          forced_check: false,
          submit_status: false
        },
        service: {
          acknowledgement: false,
          check: false,
          comment: false,
          disacknowledgement: false,
          downtime: false,
          forced_check: false,
          submit_status: false
        }
      }
    });

    const selectedResources = [host, service];

    act(() => {
      context.setSelectedResources?.(selectedResources);
    });

    await waitFor(() => {
      expect(getByText(labelForcedCheck)).toBeDisabled();
      expect(getByText(labelAcknowledge)).toBeDisabled();
      expect(getByText(labelSetDowntime)).toBeDisabled();
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);

    expect(getByText(labelDisacknowledge)).toHaveAttribute(
      'aria-disabled',
      'true'
    );
    expect(getByText(labelAddComment)).toHaveAttribute('aria-disabled', 'true');
  });

  const cannotDowntimeServicesAcl = {
    actions: {
      ...mockAcl.actions,
      service: {
        ...mockAcl.actions.service,
        downtime: false
      }
    }
  };

  const cannotAcknowledgeServicesAcl = {
    actions: {
      ...mockAcl.actions,
      service: {
        ...mockAcl.actions.service,
        acknowledgement: false
      }
    }
  };

  const cannotDisacknowledgeServicesAcl = {
    actions: {
      ...mockAcl.actions,
      service: {
        ...mockAcl.actions.service,
        disacknowledgement: false
      }
    }
  };

  const cannotDowntimeHostsAcl = {
    actions: {
      ...mockAcl.actions,
      host: {
        ...mockAcl.actions.host,
        downtime: false
      }
    }
  };

  const cannotAcknowledgeHostsAcl = {
    actions: {
      ...mockAcl.actions,
      host: {
        ...mockAcl.actions.host,
        acknowledgement: false
      }
    }
  };

  const cannotDisacknowledgeHostsAcl = {
    actions: {
      ...mockAcl.actions,
      host: {
        ...mockAcl.actions.host,
        disacknowledgement: false
      }
    }
  };

  it.each([
    [
      labelSetDowntime,
      labelSetDowntime,
      labelHostsDenied,
      cannotDowntimeHostsAcl
    ],
    [
      labelAcknowledge,
      labelAcknowledge,
      labelHostsDenied,
      cannotAcknowledgeHostsAcl
    ],
    [
      labelDisacknowledge,
      labelDisacknowledge,
      labelHostsDenied,
      cannotDisacknowledgeHostsAcl
    ]
  ])(
    'displays a warning message when trying to %p with limited ACL',
    async (_, labelAction, labelAclWarning, acl) => {
      const { getByText, getByLabelText } = renderActions(acl);

      const selectedResources = [host, service];

      act(() => {
        context.setSelectedResources?.(selectedResources);
      });

      await waitFor(() => {
        expect(
          getByLabelText(labelMoreActions).firstChild as HTMLElement
        ).toBeInTheDocument();
      });

      fireEvent.click(
        getByLabelText(labelMoreActions).firstChild as HTMLElement
      );

      fireEvent.click(getByText(labelAction));

      await waitFor(() => {
        expect(getByText(labelAclWarning)).toBeInTheDocument();
      });
    }
  );

  it.each([
    [
      labelSetDowntime,
      labelSetDowntime,
      labelSetDowntimeOnServices,
      cannotDowntimeServicesAcl
    ],
    [
      labelAcknowledge,
      labelAcknowledge,
      labelAcknowledgeServices,
      cannotAcknowledgeServicesAcl
    ],
    [
      labelDisacknowledge,
      labelDisacknowledge,
      labelDisacknowledgeServices,
      cannotDisacknowledgeServicesAcl
    ]
  ])(
    'disables services propagation option when trying to %p on hosts when ACL on services are not sufficient',
    async (_, labelAction, labelAppliesOnServices, acl) => {
      const { getByText, getByLabelText } = renderActions(acl);

      act(() => {
        context.setSelectedResources?.([host]);
      });

      fireEvent.click(
        getByLabelText(labelMoreActions).firstChild as HTMLElement
      );

      fireEvent.click(getByText(labelAction));

      await waitFor(() => {
        expect(
          getByText(labelAppliesOnServices).parentElement?.querySelector(
            'input[type="checkbox"]'
          )
        ).toBeDisabled();
      });
    }
  );

  it('disables the submit status action when one of the following condition is met: ACL are not sufficient, more than one resource is selected, selected resource is not passive', async () => {
    const { getByText, getByLabelText } = renderActions({
      actions: {
        ...mockAcl.actions,
        host: {
          ...mockAcl.actions.host,
          submit_status: false
        }
      }
    });

    act(() => {
      context.setSelectedResources?.([host, service]);
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'true'
      );
    });

    act(() => {
      context.setSelectedResources?.([host]);
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'true'
      );
    });

    act(() => {
      context.setSelectedResources?.([service]);
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).not.toHaveAttribute('aria-disabled');
    });

    act(() => {
      context.setSelectedResources?.([
        { ...service, has_passive_checks_enabled: false }
      ]);
    });

    await waitFor(() => {
      expect(getByText(labelSubmitStatus)).toHaveAttribute(
        'aria-disabled',
        'true'
      );
    });
  });

  it('disables the comment action when the ACL are not sufficient or more than one resource is selected', async () => {
    const { getByText, getByLabelText } = renderActions({
      actions: {
        ...mockAcl.actions,
        host: {
          ...mockAcl.actions.host,
          comment: false
        }
      }
    });

    act(() => {
      context.setSelectedResources?.([host, service]);
    });

    fireEvent.click(getByLabelText(labelMoreActions).firstChild as HTMLElement);

    await waitFor(() => {
      expect(getByText(labelAddComment)).toHaveAttribute(
        'aria-disabled',
        'true'
      );
    });

    act(() => {
      context.setSelectedResources?.([host]);
    });

    await waitFor(() => {
      expect(getByText(labelAddComment)).toHaveAttribute(
        'aria-disabled',
        'true'
      );
    });

    act(() => {
      context.setSelectedResources?.([service]);
    });

    await waitFor(() => {
      expect(getByText(labelAddComment)).not.toHaveAttribute('aria-disabled');
    });
  });

  it('disables the acknowledge action when selected resources have an OK or UP status', async () => {
    const { getByText } = renderActions();

    act(() => {
      context.setSelectedResources?.([
        {
          ...host,
          status: {
            name: 'UP',
            severity_code: SeverityCode.OK
          }
        },
        {
          ...service,
          status: {
            name: 'OK',
            severity_code: SeverityCode.OK
          }
        }
      ]);
    });

    await waitFor(() => {
      expect(getByText(labelAcknowledge)).toBeDisabled();
    });
  });
});
